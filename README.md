## Testowanie api hr-core

### Pobieranie informacji o pracownikach
```
curl -X GET http://test.local/wp-json/hr/v1/employees -H "Authorization: Bearer <token>
```

### Logowanie

```
curl -X POST http://<adres_strony>/wp-json/hr/v1/auth/login -H "Content-Type: application/json" -d '{
    "username":"<nazwa_uzytkownika>",
    "password": "<haslo>"
}'
```

### Dodawanie pracownika

```
curl -X POST http://<adres_strony>/wp-json/hr/v1/employees -H "Authorization: Bearer <token>" -H "Content-Type: application/json" -d '{
    "first_name": "<imie>",
    "last_name": "<nazwisko>",
    "email": "<mail>",
    "department_id": <id_dzialu>
}'
```

## Testowanie api hr-leaves

### Pobranie dostępnych typów urlopów

```
curl -X GET http://<adres_strony>/wp-json/hr/v1/leaves/types -H "Authorization: Bearer <token>"
```

### Wysłanie wniosku o urlop

```
curl -X POST "http://<adres_strony>/wp-json/hr/v1/leaves/apply" -H "Authorization: Bearer <token>" -H "Content-Type: application/json" -d '{
    "leave_type_id":1,
    "start_date":"2026-09-01",
    "end_date":"2026-09-10",
    "reason":"powod urlopu"
}'
```





