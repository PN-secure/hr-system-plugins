/**
 * Headless HR - Prywatny Widget Portalu Pracowniczego (Vanilla JS)
 * Skrypt ładuje się na stronie klienta. Renderuje logowanie, a po uwierzytelnieniu
 * udostępnia interfejs do zarządzania urlopami i czasem pracy.
 */

(function () {
    // Adres wstrzykiwany przez nasz orkiestrator PHP (np. https://api.twoj-system.pl/wp-json/hr/v1)
    const API_URL = '{{HR_API_BASE_URL}}';
    const WIDGET_CONTAINER_ID = 'hr-portal-widget';
    
    // Klucz, pod którym przechowujemy token JWT w przeglądarce
    const TOKEN_KEY = 'hr_auth_token';

    let container = document.getElementById(WIDGET_CONTAINER_ID);
    if (!container) {
        container = document.createElement('div');
        container.id = WIDGET_CONTAINER_ID;
        document.body.appendChild(container);
    }

    // 1. Wstrzykiwanie stylów (izolowane pod id kontenera)
    const injectStyles = () => {
        const style = document.createElement('style');
        style.innerHTML = `
            #hr-portal-widget { font-family: Arial, sans-serif; max-width: 900px; margin: 0 auto; color: #333; box-sizing: border-box; }
            #hr-portal-widget * { box-sizing: border-box; }
            #hr-portal-widget .hr-login-box { max-width: 400px; margin: 50px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            #hr-portal-widget .hr-login-box h2 { text-align: center; margin-top: 0; color: #0056b3; }
            #hr-portal-widget .hr-form-group { margin-bottom: 15px; }
            #hr-portal-widget label { display: block; margin-bottom: 5px; font-weight: bold; }
            #hr-portal-widget input, #hr-portal-widget select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
            #hr-portal-widget button { width: 100%; padding: 10px 15px; background-color: #0056b3; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
            #hr-portal-widget button:disabled { background-color: #999; cursor: not-allowed; }
            #hr-portal-widget .hr-btn-logout { background-color: #dc3545; width: auto; padding: 5px 10px; float: right; margin-top: -40px; }
            #hr-portal-widget .hr-alert { padding: 10px; border-radius: 4px; margin-bottom: 15px; }
            #hr-portal-widget .hr-alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
            #hr-portal-widget .hr-alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
            #hr-portal-widget .hr-dashboard { padding: 20px; background: #f9f9f9; border-radius: 8px; border: 1px solid #eee; }
            #hr-portal-widget .hr-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; }
            #hr-portal-widget table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            #hr-portal-widget th, #hr-portal-widget td { padding: 10px; border-bottom: 1px solid #eee; text-align: left; }
            #hr-portal-widget th { background: #f4f6f9; }
        `;
        document.head.appendChild(style);
    };

    // 2. Moduł API: Automatyczne dodawanie tokena do zapytań
    const apiFetch = async (endpoint, options = {}) => {
        const token = localStorage.getItem(TOKEN_KEY);
        const headers = {
            'Content-Type': 'application/json',
            ...(options.headers || {})
        };
        if (token) {
            headers['Authorization'] = `Bearer ${token}`; // Nasz klucz dostępu
        }

        const response = await fetch(`${API_URL}${endpoint}`, { ...options, headers });
        const data = await response.json().catch(() => ({}));

        if (response.status === 401 || response.status === 403) {
            // Token wygasł lub jest sfałszowany - natychmiast wylogowujemy
            logout();
            throw new Error('Sesja wygasła. Zaloguj się ponownie.');
        }
        if (!response.ok) {
            throw new Error(data.message || 'Błąd komunikacji z serwerem.');
        }
        return data;
    };

    const logout = () => {
        localStorage.removeItem(TOKEN_KEY);
        renderLogin();
    };

    // Odkodowanie tokena JWT w przeglądarce, żeby sprawdzić rolę użytkownika (bez wysyłania zapytania)
    const parseJwt = (token) => {
        try {
            const base64Url = token.split('.')[1];
            const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
            const jsonPayload = decodeURIComponent(atob(base64).split('').map(c => '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2)).join(''));
            return JSON.parse(jsonPayload).data;
        } catch (e) {
            return null;
        }
    };

    // 3. Widok Logowania
    const renderLogin = () => {
        container.innerHTML = `
            <div class="hr-login-box">
                <h2>Portal Pracowniczy</h2>
                <div id="hr-login-message"></div>
                <form id="hr-login-form">
                    <div class="hr-form-group">
                        <label>Email pracowniczy</label>
                        <input type="email" id="hr-email" required />
                    </div>
                    <div class="hr-form-group">
                        <label>Hasło</label>
                        <input type="password" id="hr-password" required />
                    </div>
                    <button type="submit" id="hr-btn-login">Zaloguj się</button>
                </form>
            </div>
        `;

        const form = document.getElementById('hr-login-form');
        const msgBox = document.getElementById('hr-login-message');
        const btn = document.getElementById('hr-btn-login');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            msgBox.innerHTML = '';
            btn.disabled = true;
            btn.textContent = 'Logowanie...';

            try {
                // Autoryzacja i pobranie tokena z wtyczki HR Core
                const result = await apiFetch('/auth/login', {
                    method: 'POST',
                    body: JSON.stringify({
                        email: document.getElementById('hr-email').value,
                        password: document.getElementById('hr-password').value
                    })
                });

                if (result.token) {
                    localStorage.setItem(TOKEN_KEY, result.token);
                    renderDashboard(); // Sukces - odpalamy panel
                }
            } catch (error) {
                msgBox.innerHTML = `<div class="hr-alert hr-alert-error">${error.message}</div>`;
            } finally {
                btn.disabled = false;
                btn.textContent = 'Zaloguj się';
            }
        });
    };

    // 4. Widok Kokpitu (Dashboard)
    const renderDashboard = async () => {
        const token = localStorage.getItem(TOKEN_KEY);
        if (!token) return renderLogin();

        const userData = parseJwt(token);
        if (!userData) return logout();

        container.innerHTML = `
            <div class="hr-dashboard">
                <h2>Witaj w Portalu</h2>
                <button class="hr-btn-logout" id="hr-logout">Wyloguj</button>
                
                <div class="hr-card" id="hr-leaves-section">
                    <h3>Twoje wnioski urlopowe</h3>
                    <div id="hr-leaves-list">Ładowanie...</div>
                </div>

                ${['hr_admin', 'manager'].includes(userData.role) ? `
                    <div class="hr-card">
                        <h3>Panel Menedżera (Wnioski zespołu)</h3>
                        <div id="hr-manager-leaves">Ładowanie...</div>
                    </div>
                ` : ''}
            </div>
        `;

        document.getElementById('hr-logout').addEventListener('click', logout);
        
        // Pobieramy dane urlopowe (Z wtyczki HR Leaves)
        loadMyLeaves();
        if (['hr_admin', 'manager'].includes(userData.role)) {
            loadManagerLeaves();
        }
    };

    // Funkcja pobierająca urlopy danego pracownika
    const loadMyLeaves = async () => {
        const listDiv = document.getElementById('hr-leaves-list');
        try {
            // Bezpieczne żądanie GET (token zostanie dodany automatycznie)
            const leaves = await apiFetch('/leaves');
            if (leaves.length === 0) {
                listDiv.innerHTML = '<p>Brak złożonych wniosków.</p>';
                return;
            }

            let html = `<table><tr><th>Od</th><th>Do</th><th>Status</th></tr>`;
            leaves.forEach(l => {
                html += `<tr><td>${l.start_date}</td><td>${l.end_date}</td><td><strong>${l.status}</strong></td></tr>`;
            });
            html += `</table>`;
            listDiv.innerHTML = html;

        } catch (error) {
            listDiv.innerHTML = `<span style="color:red">${error.message}</span>`;
        }
    };

    // Funkcja pobierająca wnioski dla menedżera
    const loadManagerLeaves = async () => {
        const managerDiv = document.getElementById('hr-manager-leaves');
        try {
            const pending = await apiFetch('/leaves/pending');
            if (pending.length === 0) {
                managerDiv.innerHTML = '<p>Brak wniosków oczekujących na decyzję.</p>';
                return;
            }

            let html = `<table><tr><th>Pracownik</th><th>Od - Do</th></tr>`;
            pending.forEach(p => {
                html += `<tr><td>${p.first_name} ${p.last_name}</td><td>${p.start_date} - ${p.end_date}</td></tr>`;
            });
            html += `</table>`;
            managerDiv.innerHTML = html;

        } catch (error) {
            managerDiv.innerHTML = `<span style="color:red">Błąd uprawnień menedżerskich.</span>`;
        }
    };

    // Inicjalizacja aplikacji
    injectStyles();
    
    // Sprawdzamy czy użytkownik ma już token. Jeśli tak -> Panel, jeśli nie -> Logowanie
    if (localStorage.getItem(TOKEN_KEY)) {
        renderDashboard();
    } else {
        renderLogin();
    }
})();