
import random
import string

from locust import HttpUser, task, between


def random_email():
    suffix = ''.join(random.choices(string.ascii_lowercase + string.digits, k=8))
    return f"loadtest_{suffix}@example.com"


class RoomBookingUser(HttpUser):
    # Each simulated user waits 1-3 seconds between actions, like a real
    # person reading the page before clicking the next thing.
    wait_time = between(1, 3)

    def on_start(self):
        """Runs once per simulated user when it starts."""
        self.email = random_email()
        self.password = "LoadTest1234"
        self.csrf_token = None

    def _grab_csrf(self, html):
        """Very small helper to pull the csrf_token value out of a page's HTML."""
        marker = 'name="csrf_token" value="'
        start = html.find(marker)
        if start == -1:
            return None
        start += len(marker)
        end = html.find('"', start)
        return html[start:end]

    @task(5)
    def browse_homepage(self):
        """Most common action: just looking at the room list."""
        self.client.get("/index.php", name="/index.php (homepage)")

    @task(3)
    def view_room_detail(self):
        """Second most common: clicking into a specific room."""
        room_id = random.choice([1, 2, 3, 4])
        self.client.get(f"/booking/room.php?id={room_id}", name="/booking/room.php (detail)")

    @task(2)
    def search_rooms(self):
        """Using the search/filter bar on the homepage."""
        query = random.choice(["Discussion", "Boardroom", "Focus", "Wi-Fi"])
        self.client.get(f"/index.php?q={query}", name="/index.php?q=... (search)")

    @task(1)
    def view_login_page(self):
        self.client.get("/login_register/login.php", name="/login_register/login.php (view)")

    @task(1)
    def register_new_account(self):
        """
        Full registration flow: load the register page (to get a fresh
        CSRF token), then submit the form. This creates real (unconfirmed)
        Cognito accounts, so don't run this against a shared/production
        pool if you don't want a pile of test accounts afterwards.
        """
        resp = self.client.get("/login_register/register.php", name="/login_register/register.php (view)")
        token = self._grab_csrf(resp.text)
        if not token:
            return

        self.client.post(
            "/login_register/register.php",
            data={
                "csrf_token": token,
                "name": "Load Test User",
                "email": self.email,
                "password": self.password,
                "confirm": self.password,
                "user_type": random.choice(["student", "faculty"]),
            },
            name="/login_register/register.php (submit)",
        )