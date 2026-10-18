import './bootstrap';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

window.Swal = Swal;

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-logout-form]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();

            Swal.fire({
                title: 'Sign out?',
                text: 'You will need to log in again to access your dashboard.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sign out',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#ef4444',
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });

    // Delegated, so forms swapped in by live page updates still ask for confirmation.
    document.addEventListener('submit', (e) => {
        const form = e.target.closest('[data-confirm-form]');

        if (!form) {
            return;
        }

        e.preventDefault();

        Swal.fire({
            title: form.dataset.confirmTitle || 'Are you sure?',
            text: form.dataset.confirmText || 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: form.dataset.confirmButton || 'Confirm',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#ef4444',
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

    const loginForm = document.querySelector('[data-login-form]');

    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = loginForm.querySelector('button[type="submit"]');
            submitBtn?.setAttribute('disabled', 'disabled');

            try {
                const response = await fetch(loginForm.action, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new FormData(loginForm),
                });

                const data = await response.json().catch(() => ({}));

                if (response.ok) {
                    await Swal.fire({
                        toast: true,
                        position: 'top',
                        icon: 'success',
                        iconColor: '#ffffff',
                        title: 'Signed in successfully',
                        background: '#22c55e',
                        color: '#ffffff',
                        timer: 2000,
                        timerProgressBar: true,
                        showConfirmButton: false,
                    });

                    window.location.href = data.redirect || '/';
                    return;
                }

                const message = data.errors
                    ? Object.values(data.errors).flat().join('\n')
                    : (data.message || 'Unable to sign in. Please try again.');

                await Swal.fire({
                    icon: 'error',
                    title: 'Login failed',
                    text: message,
                });
            } catch (error) {
                await Swal.fire({
                    icon: 'error',
                    title: 'Something went wrong',
                    text: 'Please check your connection and try again.',
                });
            } finally {
                submitBtn?.removeAttribute('disabled');
            }
        });
    }
});
