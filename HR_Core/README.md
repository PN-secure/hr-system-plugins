## Testowanie api

### Logowanie

```
curl -X POST http://test.local/wp-json/hr/v1/auth/login -H "Content-Type: application/json" -d '{
    "username":"admin",
    "password": "admin"
}'
```

### Dodawanie pracownika

```
curl -X POST http://test.local/wp-json/hr/v1/employees -H "Authorization: Bearer <token>" -H "Content-Type: application/json" -d '{
    "first_name": "Jan",
    "last_name": "Kowalski",
    "email": "jan@kowalski.com",
    "department_id": 2
}'
```