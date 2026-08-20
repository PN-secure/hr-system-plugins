/**
 * Headless HR - Publiczny Widget Kariery (Vanilla JS)
 * Skrypt ładuje się na stronie klienta, pobiera oferty pracy z API i pozwala wysłać aplikację z CV.
 */

(function () {
    // Zmienna {{HR_API_BASE_URL}} zostanie w locie podmieniona przez nasz plik PHP (HR_Career_Rewrite)
    const API_URL = '{{HR_API_BASE_URL}}';
    
    // Szukamy kontenera, w którym klient chce osadzić widget
    const WIDGET_CONTAINER_ID = 'hr-career-widget';
    let container = document.getElementById(WIDGET_CONTAINER_ID);

    // Jeśli klient nie stworzył kontenera, tworzymy go sami i doczepiamy na dole strony
    if (!container) {
        container = document.createElement('div');
        container.id = WIDGET_CONTAINER_ID;
        document.body.appendChild(container);
    }

    // Wstrzykujemy podstawowy CSS, izolując go prefiksem #hr-career-widget, aby nie psuć strony klienta
    const injectStyles = () => {
        const style = document.createElement('style');
        style.innerHTML = `
            #hr-career-widget { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; color: #333; }
            #hr-career-widget .hr-job-card { border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 5px; }
            #hr-career-widget .hr-job-title { margin: 0 0 10px 0; color: #0056b3; }
            #hr-career-widget .hr-job-meta { font-size: 0.85em; color: #666; margin-bottom: 10px; }
            #hr-career-widget .hr-form-group { margin-bottom: 15px; }
            #hr-career-widget label { display: block; margin-bottom: 5px; font-weight: bold; }
            #hr-career-widget input, #hr-career-widget select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
            #hr-career-widget button { padding: 10px 15px; background-color: #0056b3; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
            #hr-career-widget button:disabled { background-color: #999; cursor: not-allowed; }
            #hr-career-widget .hr-alert { padding: 10px; border-radius: 4px; margin-bottom: 15px; }
            #hr-career-widget .hr-alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
            #hr-career-widget .hr-alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
            #hr-career-widget .hr-loader { text-align: center; padding: 20px; font-style: italic; color: #666; }
        `;
        document.head.appendChild(style);
    };

    // Renderowanie powiadomień (sukces/błąd)
    const renderAlert = (message, isError = false) => {
        const alertDiv = document.createElement('div');
        alertDiv.className = `hr-alert ${isError ? 'hr-alert-error' : 'hr-alert-success'}`;
        alertDiv.textContent = message;
        return alertDiv;
    };

    // Główna funkcja pobierająca dane z Twojego API (Wtyczka HR Recruitment)
    const fetchJobs = async () => {
        container.innerHTML = '<div class="hr-loader">Ładowanie ofert pracy...</div>';
        try {
            const response = await fetch(`${API_URL}/jobs`);
            if (!response.ok) throw new Error('Błąd komunikacji z serwerem HR.');
            const jobs = await response.json();
            renderWidget(jobs);
        } catch (error) {
            container.innerHTML = '';
            container.appendChild(renderAlert('Nie udało się załadować ofert pracy. Spróbuj ponownie później.', true));
        }
    };

    // Renderowanie głównego interfejsu
    const renderWidget = (jobs) => {
        container.innerHTML = ''; // Czyszczenie kontenera

        // 1. Sekcja ofert pracy
        const jobsSection = document.createElement('div');
        jobsSection.innerHTML = '<h2>Aktualne oferty pracy</h2>';
        
        if (jobs.length === 0) {
            jobsSection.innerHTML += '<p>Obecnie nie prowadzimy rekrutacji.</p>';
        } else {
            jobs.forEach(job => {
                const card = document.createElement('div');
                card.className = 'hr-job-card';
                card.innerHTML = `
                    <h3 class="hr-job-title">${job.title}</h3>
                    <div class="hr-job-meta">Dział: ${job.department_name || 'Brak'}</div>
                    <div class="hr-job-desc">${job.description}</div>
                `;
                jobsSection.appendChild(card);
            });
        }
        container.appendChild(jobsSection);

        // 2. Sekcja formularza aplikacyjnego (Pokazujemy tylko, jeśli są oferty)
        if (jobs.length > 0) {
            const formSection = document.createElement('div');
            formSection.innerHTML = '<h2 style="margin-top: 40px;">Złóż aplikację</h2>';
            
            const messageBox = document.createElement('div');
            formSection.appendChild(messageBox);

            const form = document.createElement('form');
            form.id = 'hr-application-form';
            
            // Generowanie opcji do selecta
            const jobOptions = jobs.map(j => `<option value="${j.id}">${j.title}</option>`).join('');

            form.innerHTML = `
                <div class="hr-form-group">
                    <label>Wybierz stanowisko *</label>
                    <select name="job_id" required>
                        <option value="">-- Wybierz --</option>
                        ${jobOptions}
                    </select>
                </div>
                <div class="hr-form-group">
                    <label>Imię *</label>
                    <input type="text" name="first_name" required />
                </div>
                <div class="hr-form-group">
                    <label>Nazwisko *</label>
                    <input type="text" name="last_name" required />
                </div>
                <div class="hr-form-group">
                    <label>E-mail *</label>
                    <input type="email" name="email" required />
                </div>
                <div class="hr-form-group">
                    <label>Telefon</label>
                    <input type="text" name="phone" />
                </div>
                <div class="hr-form-group">
                    <label>CV (Tylko plik PDF) *</label>
                    <input type="file" name="cv" accept=".pdf" required />
                </div>
                <button type="submit" id="hr-submit-btn">Wyślij aplikację</button>
            `;

            // Obsługa wysyłki formularza
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                messageBox.innerHTML = '';
                const submitBtn = document.getElementById('hr-submit-btn');
                submitBtn.disabled = true;
                submitBtn.textContent = 'Wysyłanie...';

                // Budowanie obiektu FormData do wysyłki pliku binarnego (CV)
                const formData = new FormData(form);

                try {
                    const response = await fetch(`${API_URL}/apply`, {
                        method: 'POST',
                        body: formData // Nie ustawiamy Content-Type! Przeglądarka sama ustawi multipart/form-data boundary.
                    });

                    const result = await response.json();

                    if (response.ok && result.success) {
                        messageBox.appendChild(renderAlert('Aplikacja wysłana pomyślnie. Dziękujemy!'));
                        form.reset();
                    } else {
                        messageBox.appendChild(renderAlert(result.message || 'Wystąpił błąd.', true));
                    }
                } catch (error) {
                    messageBox.appendChild(renderAlert('Błąd sieci. Sprawdź połączenie internetowe.', true));
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Wyślij aplikację';
                }
            });

            formSection.appendChild(form);
            container.appendChild(formSection);
        }
    };

    // Uruchomienie widgetu
    injectStyles();
    fetchJobs();

})();