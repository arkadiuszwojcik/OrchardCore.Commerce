# Rozmiary i usługi dla przesyłek




**Na tej stronie**

## InPost Paczkomat®

Przesyłki Paczkomat®.


#### Usługi oferowane przez przewoźnika

| **ID Oferty** | **Nazwa oferty** |
| --- | --- |
| `inpost_locker_standard` | Przesyłka Paczkomat® - standardowa |
| `inpost_locker_allegro` | Przesyłka Paczkomat® - Allegro Paczkomat® InPost. |
| `inpost_locker_pass_thru` | Przesyłka Paczkomat® - Podaj Dalej |
| `inpost_letter_allegro` | Przesyłka kurierska - Allegro MiniKurier24 InPost |
| `inpost_courier_allegro` | Przesyłka kurierska - Allegro Kurier24 InPost. |
| `inpost_courier_c2c` | Przesyłka kurierska - InPost Kurier C2C (usługa dla klienta detalicznego - prepaid) |

W przypadku podania paczkopunktu jako punktu odbioru (`target_point": "POP-WAW364"`) zostanie podmieniony serwis z `"service": "inpost_locker_standard"` na `"service": "inpost_locker_customer_service_point"`.

#### Usługi dodatkowe

| **Usługa** | **Identyfikator** |
| --- | --- |
| Pobranie | `cod` |
| Ubezpieczenie | `insurance` |

####   
Wymiary i wagi przesyłek

Poniżej prezentujemy predefiniowane szablony wymiarów i wag dla przesyłek. Szablon ma na stałe przypisane maksymalne wymiary i wagę.

Predefiniowane szablony wymiarów i wag dla przesyłek `inpost_locker_standard`, `inpost_locker_allegro`, `inpost_locker_pass_thru` i `inpost_courier_c2c` :

| **Nazwa szablonu** | **Wymiary** | **Waga** | **Opis** | **Uwagi** |
| --- | --- | --- | --- | --- |
| `small` | 8 x 38 x 64 cm | do 25 kg | Gabaryt A |  |
| `medium` | 19 x 38 x 64 cm | do 25 kg | Gabaryt B |  |
| `large` | 41 x 38 x 64 cm | do 25 kg | Gabaryt C |  |
| `xlarge` | 50 x 50 x 80 cm | do 25 kg | Gabaryt D | Dostępne tylko przesyłek kurierskich `inpost_courier_c2c` |


Predefiniowane szablony wymiarów i wag dla przesyłek `inpost_letter_allegro:`

| **Nazwa szablonu** | **Wymiary** | **Waga** | **Opis** |
| --- | --- | --- | --- |
| `letter_a` | 8 x 38 x 64 cm | do 10 kg | Gabaryt A |
| `letter_b` | 19 x 38 x 64 cm | do 10 kg | Gabaryt B |
| `letter_c` | suma wymiarów: 160 cm | do 10 kg | Gabaryt C |


Dla serwisu `inpost_courier_allegro` nie ma predefiniowanych szablonów. Należy przekazywać wymiary i wagę.

Przykład:

```json
   "parcels": { 
    	"dimensions": {
            "length": "120",
            "width": "34",
            "height": "81",
            "unit": "mm"
         },
        "weight": {
            "amount": "20",
            "unit": "kg"
        } 
    }
```

---


## InPost Kurier


####   
Usługi oferowane przez przewoźnika

| **Identyfikator usługi w ShipX** | **Nazwa usługi** |
| --- | --- |
| `inpost_courier_standard` | Przesyłka kurierska standardowa |
| `inpost_courier_express_1000` | Przesyłka kurierska z doręczeniem do 10:00 |
| `inpost_courier_express_1200` | Przesyłka kurierska z doręczeniem do 12:00 |
| `inpost_courier_express_1700` | Przesyłka kurierska z doręczeniem do 17:00 |
| `inpost_courier_palette` | Przesyłka kurierska Paleta Standard |
| `inpost_courier_alcohol` | Przesyłka kurierska eSmartMix z elektronicznym obiegiem dokumentów i potwierdzeniem danych odbiorcy |


#### Wymiary i wagi przesyłek

| **Wymiary** | **Opis** |
| --- | --- |
| 120 x 80 x 180 cm Waga od 50 do 800 kg Dostępne wyłącznie dla serwisu `inpost_courier_palette` | Paleta |
| Maksymalne wymiary: -  350 x 240 x 240 cm Waga: - od 0.01kg do 50 kg  Ilość: - domyślna wartość 1, - maksymalna 99 ( tylko dla paczki krajowej ) | Paczka |

####   
Usługi dodatkowe

| **Usługa** | **Identyfikator** | **Opis** |
| --- | --- | --- |
| Ubezpieczenie | `insurance` | Ubezpieczenie przesyłki. |
| Pobranie | `cod` | Pobranie za przesyłkę. |
| Serwis SMS | `sms` | Powiadomienie o przesyłce via SMS. |
| Serwis e-mail | `email` | Powiadomienie o przesyłce via e-mail. |
| Doręczenie sobota | `saturday` | Doręczenie przesyłki w sobotę. |
| Doręczenie od 17:00 do 20:00 | `dor1720` | Doręczenie pomiędzy 17:00 a 20:00 Usługa dodatkowa w serwisie krajowym obejmująca doręczenie przesyłki do Odbiorcy w dni robocze w godzinach wieczornych pomiędzy 17:00 a 20:00, dostępna dla wszystkich miejscowości w Polsce. Usługa doliczana jest do ceny podstawowej usługi Kurier Standard. Usługa dostępna wyłącznie w serwisie `inpost_courier_standard` |
| Doręczenie na godzinę | `forhour_9``forhour_10``forhour_11``forhour_12``forhour_13``forhour_14``forhour_15``forhour_16``forhour_17` | Usługa dodatkowa w serwisie krajowym obejmująca doręczenie przesyłki w dni robocze do określonej przez Nadawcę pełnej godziny. Doręczenie realizowane jest tylko dla godzin pomiędzy 9:00 a 17:00 w przedziale czasowym do 30 minut przed wyznaczoną godziną. W przypadku przesyłek z doręczeniem pomiędzy godziną 9:00 a 12:00 doręczenie przesyłek możliwe jest tylko w wybranych miejscowościach. Przesyłki z zastrzeżeniem godziny pomiędzy 12:00 a 17:00 doręczane są w całym kraju. Koszt usługi Doręczenie na godzinę doliczany jest do ceny podstawowej usługi przed którą następuje, odpowiednio: Kurier Doręczenie do 10:00, Kurier Doręczenie do 12:00 lub Kurier. |
| Zwrot dokumentów | `rod`  | Zwrot dokumentów |

####   
Usługi dodatkowe w usługach


| Nazwa usługi | insurance | cod | sms | email | saturday | dor1720 | rod | forhour\_9 | forhour\_10 | forhour\_11 | forhour\_12 | forhour\_13 | forhour\_14 | forhour\_15 | forhour\_16 | forhour\_17 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `inpost_courier_standard` |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |
| `inpost_courier_express_1000` |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |
| `inpost_courier_express_1200` |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |
| `inpost_courier_express_1700` |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |
| `inpost_courier_palette` |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |
| `inpost_courier_c2c` |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |

####   
Wymiary i wagi przesyłek


Dla serwisów kurierskich zaleca się podawanie rzeczywistych wymiarów i wagi dla każdej paczki.  

```json
   "parcels": { 
    	"dimensions": {
            "length": "120",
            "width": "34",
            "height": "81",
            "unit": "mm"
         },
        "weight": {
            "amount": "20",
            "unit": "kg"
        } 
    }
```

---


## Rozmiar przesyłki na podstawie przekazywanych parametrów

Aplikacja umożliwia wygenerowanie przesyłki z rozmiarem, bez podawania jego nazwy. Rozmiar generowany jest na podstawie przekazywanych parametrów, poniżej został umieszczony przykład:

|  |
| --- |
| `   "parcels": {      	"dimensions": {             "length": "120",             "width": "34",             "height": "81",             "unit": "mm"          },         "weight": {             "amount": "20",             "unit": "kg"         }      }` |

Znalezienie odpowiedniego rozmiaru przesyłki, bez podawania jego nazwy, uwarunkowane jest przekazaniem wszystkich parametrów obiektów `dimensions`oraz `weight`

Poniższa tabela przedstawia warunki jakie należy spełnić, aby umożliwić wygenerowanie rozmiaru na podstawie przekazywanych parametrów.

| **Rozmiar przesyłki** | **Serwis** | **Parametr: Umowne przedziały i ich wartości** |
| --- | --- | --- |
| `letter_a` | `inpost_letter_allegro` | `lenght: 1 - 325``width: 1 - 230``height: 1 - 80``weight: 1 - 2` |
| `small` | `inpost_locker_standard``inpost_locker_allegro``inpost_locker_pass_thru` | `lenght: 1 - 380``width: 1 - 640``height: 1 - 80` `(mniejsze lub równe 80)``weight: 0 - 25` |
| `medium` | `inpost_locker_standard``inpost_locker_allegro``inpost_locker_pass_thru` | `lenght: 1 - 380``width: 1 - 640``height: 81 - 190` `(mniejsze lub równe 190)``weight: 0 - 25` |
| `large` | `inpost_locker_standard``inpost_locker_allegro``inpost_locker_pass_thru` | `lenght: 1 - 380``width: 1 - 640``height: 191 - 410` `(mniejsze lub równe 410)``weight: 0 - 25` |
