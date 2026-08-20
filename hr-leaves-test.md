# testy api

    1. class-hr-api-requests.php      <-- Endpointy /leaves: /apply, /pending /my-requests, /(?P<id>\d+)/status
    2. class-hr-api-delegations.php   <-- Endpointy /delegations: /apply
    3. class-hr-api-leave-types.php   <-- Endpointy /leaves: /types
    4. class-hr-api-time-tracking.php <-- Endpointy /time: /clock-in, clock-out, timesheet

## class-hr-api-requests.php

Przetestowane zostały /apply, /pending, /my-requests - działają bez zarzutu

id/status - niby działa ale nie wiadomo czy nie rozsypał innych rzeczy

## class-hr-api-delegations.php

Działa ale nie trzeba będzie całość jeszcze raz przetestować

## class-hr-api-leave-types.php

Działa bez problemów

## class-hr-api-time-tracking.php

Wszystko działa