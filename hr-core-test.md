# testy api

    1. class-hr-api-auth.php       <-- Endpointy: /login, /refresh-token
    2. class-hr-api-employees.php  <-- Endpointy: /employees (GET, POST, PUT, DELETE)
    3. class-hr-api-departments.php<-- Endpointy: /departments
    4. class-hr-api-org-chart.php  <-- Endpointy: /org-chart (Drzewo struktury firmy)

## 1. class-hr-api-auth.php       <-- Endpointy: /login, /refresh-token

    tak naprawde to nie ma /refresh-token, ale jest /auth/me

    /wp-json/hr/v1/auth/me  GET  <- pobiera informacje na podstawie tokena jwt
        wysyłamy GET'a z headerem Authorization: Bearer <token>
        dostajemy info:

        {
            "id": 1,
            "email": "dev-email@wpengine.local",
            "name": "test",
            "role": "hr_admin"
        }

        endpoint działa :)

    /wp-json/hr/v1/login służy do logowania i działa

## 2. class-hr-api-employees.php  <-- Endpointy: /employees (GET, POST, PUT, DELETE)

    curl -X GET http://test.local/wp-json/hr/v1/employees -H "Authorization: Bearer <token>

    curl -X POST http://<adres_strony>/wp-json/hr/v1/employees -H "Authorization: Bearer <token>" -H "Content-Type: application/json" -d '{
        "first_name": "<imie>",
        "last_name": "<nazwisko>",
        "email": "<mail>",
        "department_id": <id_dzialu>
    }'

    tu wszystko działa :) 

## 3. class-hr-api-departments.php  <-- Endpointy: /departments
    GET działa
    POST działa (potencjalny problem jest taki że można dodać pare departamentów o tej samej nazwie)
    DEL działą

    wszystkie endpointy nie pozwalają na wykonanie zadania przy błędnym tokenie, czyli git

## 4. class-hr-api-org-chart.php  <-- Endpointy: /org-chart (Drzewo struktury firmy)

    GET /team/id działa
    POST /org-chart/assign działą