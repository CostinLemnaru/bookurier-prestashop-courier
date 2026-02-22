# Modul Bookurier Courier pentru PrestaShop

- integrare Bookurier (livrare standard),
- integrare SameDay Locker (livrare la locker),
- generare AWB automata sau manuala din configurabil din Back Office.

Compatibilitate: PrestaShop `1.7+`, `8.x`, `9.x`.

## Functionalitate

- Creeaza 2 carriers la instalare: `Bookurier` si `Sameday Locker`.
- Genereaza AWB automat pe statusurile selectate.
- Permite generare manuala AWB din comanda (daca `Auto generate AWB` este OFF).
- Pentru `Sameday Locker`, clientul selecteaza lockerul in checkout.
- Afiseaza in Back Office un panel cu: numarul de AWB, status si link de download PDF.

## 1. Instalare

1. Copiaza folderul modulului in `modules/bookurier`.
2. In Back Office mergi la `Modules > Module Manager`.
3. Cauta `Bookurier Courier` si apasa `Install`.

## 2. Configurare rapida

Mergi la `Modules > Module Manager > Bookurier Courier > Configure`.

Completeaza:

- `Bookurier API Username`
- `Bookurier API Password`
- `Bookurier API Key (Tracking)`
- `Bookurier Default Pickup Point`
- `Bookurier Default Service`

Setari AWB:

- `Auto generate AWB` = ON/OFF
- `Auto AWB allowed statuses` = statusurile care declanseaza AWB automat

Setari SameDay Locker (optional):

- `Enable SameDay` = Yes
- `SameDay Environment` = Production (implicit) / Demo
- `SameDay API Username`
- `SameDay API Password`
- `Save` (pentru incarcare lista pickup points)
- selecteaza `SameDay Pickup Point`
- selecteaza `SameDay Package Type`
- apasa `Sync SameDay Lockers`

## 3. Cum functioneaza AWB

- Comanda cu carrier `Bookurier` -> AWB se genereaza prin Bookurier.
- Comanda cu carrier `Sameday Locker` -> AWB se genereaza prin SameDay, cu lockerul selectat in checkout.
- Daca `Auto generate AWB = OFF`, folosesti butonul `Generate AWB` din comanda BO.

## 4. Unde vezi AWB-ul

In pagina comenzii din Back Office, in panelul `Bookurier AWB`:

- numarul de AWB,
- statusul AWB,
- butonul `Download AWB PDF`.

## Probleme frecvente

- Nu apar lockere in checkout: verifica `Enable SameDay = Yes` si ruleaza `Sync SameDay Lockers`.
- Nu se genereaza AWB automat: verifica statusurile din `Auto AWB allowed statuses`.
- Statusul AWB ramane generic: completeaza `Bookurier API Key (Tracking)`.

## Requirements / Prerequisites

- PrestaShop `1.7+`, `8.x` sau `9.x`.
- Modulul instalat in `modules/bookurier` si activat din Back Office.
- Credentiale Bookurier valide (`API Username`, `API Password`); pentru status tracking: `API Key`.
- Pentru SameDay Locker (optional): credentiale SameDay valide + `Sync SameDay Lockers` rulat cu succes.
- Serverul magazinului trebuie sa permita conexiuni HTTPS outbound catre API-urile Bookurier/SameDay.
- Extensia PHP `cURL` activa (modulul foloseste fallback cURL pentru request-uri HTTP).
- Tarifele carrier-elor configurate in PrestaShop (`Shipping > Carriers`) conform regulilor magazinului.
