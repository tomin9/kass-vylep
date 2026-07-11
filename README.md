# Výlep plagátov

WordPress plugin pre OZ Ars Preuge / KaSS Prievidza na evidenciu výlepu plagátov, správu odberateľov, cenník a generovanie podkladov k fakturácii.

## Inštalácia

1. V administrácii WordPressu choď do **Pluginy → Pridať nový → Nahrať plugin**.
2. Nahraj súbor `kass-vylep.zip`.
3. Klikni **Inštalovať** a potom **Aktivovať**.
4. V ľavom menu sa objaví položka **Výlep plagátov**.

Pri aktivácii sa automaticky vytvoria databázové tabuľky a naplní sa východiskový cenník.

## Časti pluginu

**Čo neprelepiť** – hlavná stránka. Vyber utorok (deň výlepu) a zobrazí sa zoznam plagátov, ktoré sú v tom týždni ešte aktívne a nesmú sa prelepiť. Dá sa listovať po týždňoch a zoznam vytlačiť.

**Evidencia výlepov** – zoznam všetkých výlepov po rokoch, ako v pôvodnom exceli. Pri každom zázname je odkaz na úpravu, generovanie faktúry a zmazanie. Pri pridávaní výlepu sa dátum automaticky zarovná na utorok a koniec výlepu sa vypočíta podľa počtu týždňov.

**Odberatelia** – databáza organizácií so všetkými fakturačnými údajmi (IČO, DIČ, IČ DPH, IBAN…). Pri tvorbe faktúry sa údaje načítajú automaticky.

**Cenník** – upraviteľná matica cien za kus podľa formátu (A1–A4) a dĺžky výlepu (1–5 týždňov).

**Podklad k fakturácii** – generátor dokumentu na veľkosť A4 s automatickým výpočtom. Vyber odberateľa, doplň položky a vytlač (alebo ulož ako PDF cez tlač prehliadača).

## Logika výlepu

Výlep prebieha vždy v **utorok**. Plagát vylepený v daný utorok ostáva vylepený X týždňov, t. j. do utorka o X týždňov neskôr. Plugin tento dátum počíta automaticky a podľa neho určuje, ktoré plagáty sú v ktorom týždni aktívne.

## Prístup

Plugin je dostupný iba prihláseným používateľom s oprávnením `manage_options` (správca). Nič sa nezobrazuje na verejnej časti webu.
