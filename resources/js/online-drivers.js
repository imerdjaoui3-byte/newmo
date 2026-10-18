/**
 * Keeps a live list of online drivers for the dispatch screens.
 *
 * - The server list (dataUrl) is the source of truth and is re-polled slowly.
 * - Every new position (DriverLocationUpdated) adds or moves a driver instantly.
 * - Drivers join the "drivers.online" presence channel from their dashboard, so
 *   closing it removes them at once, unless OwnTracks still tracks them in the
 *   background.
 *
 * Without Echo it falls back to polling every few seconds.
 */
export function watchOnlineDrivers({ dataUrl, onChange }) {
    const drivers = new Map();
    // Drivers whose dashboard just closed: the server keeps them for a few more seconds, so hide them until they ping again.
    const leftDriverIds = new Set();

    const isHidden = (driver) => leftDriverIds.has(driver.id) && !driver.background;
    const emit = () => onChange([...drivers.values()]);

    async function refresh() {
        try {
            const response = await fetch(dataUrl, { headers: { Accept: 'application/json' } });
            const data = await response.json();

            drivers.clear();
            data.drivers.filter((driver) => !isHidden(driver)).forEach((driver) => drivers.set(driver.id, driver));
            emit();
        } catch (error) {
            // silently retry on the next poll
        }
    }

    refresh();

    if (!window.Echo) {
        setInterval(refresh, 8000);

        return { refresh };
    }

    window.Echo.private('drivers.tracking').listen('DriverLocationUpdated', (event) => {
        leftDriverIds.delete(event.driver.id);
        drivers.set(event.driver.id, event.driver);
        emit();
    });

    window.Echo.join('drivers.online')
        .joining((member) => member.driver_id && leftDriverIds.delete(member.driver_id))
        .leaving((member) => {
            if (!member.driver_id) {
                return;
            }

            leftDriverIds.add(member.driver_id);

            const driver = drivers.get(member.driver_id);
            if (driver && isHidden(driver)) {
                drivers.delete(member.driver_id);
                emit();
            }
        });

    setInterval(refresh, 60000);

    return { refresh };
}
