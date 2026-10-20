<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index(): View
    {
        $users = User::where(fn (Builder $query) => $query->ranked()->orWhereNull('rank'))
            ->with('driver')
            ->orderByRaw('`rank` is null desc')
            ->latest()
            ->get();

        return view('users.index', compact('users'));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): View
    {
        return view('users.create');
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $user = User::create(collect($data)->only(['name', 'email', 'password', 'rank'])->all());

            if ($user->rank === 3) {
                Driver::create([
                    'user_id' => $user->id,
                    'phone' => $data['phone'],
                    'car' => $data['car'] ?? null,
                    'color' => $data['color'] ?? null,
                ]);
            }
        });

        return redirect()->route('users.index')->with('status', 'User created successfully.');
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user): View
    {
        abort_if($user->rank === 0, 404);

        $user->load('driver');

        return view('users.edit', compact('user'));
    }

    /**
     * Update the specified user in storage.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        DB::transaction(function () use ($data, $user) {
            $user->update(collect($data)->only(['name', 'email', 'password', 'rank'])->all());

            if ($user->rank === 3) {
                Driver::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'phone' => $data['phone'],
                        'car' => $data['car'] ?? null,
                        'color' => $data['color'] ?? null,
                    ]
                );
            }
        });

        return redirect()->route('users.index')->with('status', 'User updated successfully.');
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        return redirect()->route('users.index')->with('status', 'User deleted successfully.');
    }
}
