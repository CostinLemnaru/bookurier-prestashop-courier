# Bookurier PrestaShop Courier Module

Courier module repository (`bookurier`).

This repo is mounted by the environment repo:
- `git@github.com:CostinLemnaru/bookurier-prestashop.git`

## Current architecture baseline

- `src/Client/Bookurier/*`: Bookurier API client + interface
- `src/Client/Sameday/*`: SameDay API client + interface
- `src/DTO/Bookurier/*`: Bookurier DTOs
- `src/DTO/Sameday/*`: SameDay DTOs
- `src/Logging/*`: module logging abstraction (PrestaShop logger adapter)
- `src/Exception/*`: shared exceptions
