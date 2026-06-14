=== InPost PL ===
Contributors: inspirelabs
Tags: inpost, paczkomaty, etykiety, przesyłki
Requires at least: 5.3
Tested up to: 6.9
Requires PHP: 7.2
Stable tag: 1.8.8
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html
 
InPost PL dla WooCommerce to dedykowana wtyczka do integracji, stworzona z myślą o małych i średnich firmach, które chcą w szybki i wygodny sposób zintegrować się z usługami InPost.

== Description ==

InPost PL to wtyczka do integracji, którą stworzyliśmy z myślą o małych i średnich firmach prowadzących sprzedaż na platformie WooCommerce.
Dzięki niej szybko i wygodnie połączysz się z naszymi systemami InPost, co pozwoli Ci na sprawne zarządzanie wysyłkami i sprawi, że obsługa wysyłek będzie prostsza i szybsza.

### Co zyskasz instalując wtyczkę?

* Dostęp do wszystkich naszych usług InPost m.in. InPost Paczkomat® 24/7, Kurier InPost, Paczka w Weekend, Szybkie Zwroty
* Pewność, że masz aktualną i czytelną mapę naszych punktów odbioru (urządzeń Paczkomat® oraz PaczkoPunktów), dzięki której Twoi Klienci będą mogli w łatwy i wygodny sposób kierować swoje zamówienia z Twojego e-sklepu
* Więcej czasu dla swojego biznesu, dzięki zautomatyzowanemu procesowi transferu danych adresata oraz punktu odbioru w celu realizacji zamówienia
* Proste i szybkie generowanie etykiet nadawczych, zleceń podjazdów kurierskich oraz przygotowanie zwrotów przesyłek
* Możliwość włączenia lub wyłączenia konkretnych produktów z dostawy InPost
* Możliwość zarządzania i monitorowania przesyłek oraz złożonych zleceń odbioru paczek

Po zainstalowaniu wtyczki skonfiguruj sposób dostawy InPost oraz ceny poszczególnych usług, dzięki czemu Twoi Klienci będą mogli wybrać swój ulubiony sposób dostawy przesyłek.

**Pełną dokumentację oraz instrukcję instalacji wtyczki znajdziesz na stronie:**
[https://dokumentacja-inpost.atlassian.net/wiki/spaces/PL/pages/61833233/WooCommerce](https://dokumentacja-inpost.atlassian.net/wiki/spaces/PL/pages/61833233/WooCommerce)

**W przypadku pytań/problemów związanych z wtyczką zachęcamy do skorzystania z naszego [formularza InPost](https://inpost.pl/formularz-wsparcie)**.


== Screenshots ==

1. screenshot-1.png
2. screenshot-2.png
3. screenshot-3.png
4. screenshot-4.png
5. screenshot-5.png


== Changelog ==

= 1.8.8 =
* Fix: webhook initial  status
* Fix: product table Polylang compatibility

= 1.8.7 =
* Fix: webhook URL in settings

= 1.8.6 =
* Fix: dispatch orders (Zamawianie kuriera)

= 1.8.5 =
* Fix: validation of international phone numbers

= 1.8.4 =
* Fix: getting Shipping zones

= 1.8.3 =
* Fix: get weight for courier shipments

= 1.8.2 =
* Fix: default send method in metabox
* Fix: enable/disable InPost methods for products

= 1.8.1 =
* Fix: webhook statuses updates
* Fix: integration with Flexible Shipping

= 1.8.0 =
* Fix: Smart Courier

= 1.7.9 =
* Fix: weight value for Courier shipments

= 1.7.8 =
* Fix: weight value from template for Courier shipments

= 1.7.7 =
* Feat: new setting for InPost Paczkomaty 24/7

= 1.7.6 =
* Feat: new setting for auto shipment creating
* Feat: new setting for webhooks update order status

= 1.7.5 =
* Feat: new settings for shipping methods
* Feat: webhooks updates

= 1.7.4 =
* Fix: Courier templates

= 1.7.3 =
* Fix: bulk order creating

= 1.7.2 =
* Feat: templates for courier shipments
* Feat: new settings - terms of delivery
* Fix: validation if parcel locker selected

= 1.7.1 =
* Feat: additional possibility to choose Locker ID on Thank You Page for some cases
* Fix: integration with Flexible Shipping plugin

= 1.7.0 =
* Fix: validation if Pickup chosen in Checkout Block

= 1.6.9 =
* Feat: update JS lib for map

= 1.6.8 =
* Fix: translations

= 1.6.7 =
* Fix: compatibility with InPost Pay (get parcel locker details from order meta)
* Fix: emails with tracking number(s)
* Fix: disappearing selected locker on JS event "updated_checkout"

= 1.6.6 =
* Fix: Kurier Pobranie - limit 15 000 zł

= 1.6.5 =
* Fix: integration with Inpost International plugin
* Fix: Paczka w Weekend Pobranie
* Feat: warning message about Delivery point missed for Google Pay or Apple Pay method (only on Checkout page)

= 1.6.4 =
* Fix: PHP warning for digital products in checkout
* Fix: Duplicated DB queries for Shipping Zones

= 1.6.3 =
* Fix: empty locker data on 'updated_checkout' JS event for JS-mode of button
* Fix: filter shipping packages
* Fix: enquene of "inpost-pl.js" script

= 1.6.2 =
* Fix: get labels for Smart Courier
* Feat: insurance setting moved to shipping settings method
* Feat: Paczka w Weekend COD
* Feat: new option - shipping cost based on qty of products in cart

= 1.6.1 =
* Fix: integration with Flexible Shipping

= 1.6.0 =
* Fix: translations
* Feat: new Bulk actions for orders

= 1.5.9 =
* Fix: integration with Flexible Shipping for Checkout blocks
* Feat: new Product settings page

= 1.5.8 =
* Fix: alternative JS-method of map button
* Fix: hide checkbox "Ship to different address"

= 1.5.7 =
* Fix: locker data view in new Checkout
* Fix: SmartCourier in filter on page "Shipments"

= 1.5.6 =
* Fix: error on search on page "Shipments"

= 1.5.5 =
* Fix: pagination on page "Shipments"

= 1.5.4 =
* Fix: change COD amount for Courier methods

= 1.5.3 =
* Fix: validation on Checkout Blocks on country change
* Fix: double map widget

= 1.5.2 =
* Fix: message details for error during package createing

= 1.5.1 =
* Feat: print post confirmation
* Fix: html of icons

= 1.5.0 =
* Feat: new setting for order insurance
* Fix: choose qty of parcels for "Przesyłka wielopaczkowa"

= 1.4.9 =
* Fix: compatibility error for Courier parcels
* Fix: change COD amount before creating parcel 

= 1.4.8 =
* Feat: add feature "SMS" and "Email" notifications for Courier methods
* Fix: validation error for block checkout

= 1.4.7 =
* Feat: add feature "Przesyłka wielopaczkowa" for method Courier COD (pobranie)

= 1.4.6 =
* Fix: compatibility with CheckoutWC plugin
* Fix: map params for Checkout blocks checkout

= 1.4.5 =
* Fix: error on order status change
* Feat: add feature "Przesyłka wielopaczkowa" for method Courier standard

= 1.4.4 =
* Fix: integration with Flexible Shipping for feature "Druga paczka"
* Fix: colspan on Checkout page
* Fix: adding buyer's note for order in Bulk creating mode

= 1.4.3 =
* Fix: error in JS for Kurier COD method
* Feat: printing multiple labels for additional packages

= 1.4.2 =
* Fix: validation  error for SmartCourier on checkout

= 1.4.1 =
* Fix: print label format

= 1.4.0 =
* Feat: nowa metoda dostawy "Smart Courier"

= 1.3.9 =
* Fix: błąd kwoty ubezpieczenia

= 1.3.8 =
* Korekta wyświetlania kwoty ubezpieczenia

= 1.3.7 =
* Więcej informacji o adresie punktu
* Inne drobne poprawki

= 1.3.6 =
* Funkcjonalność - utworzenie drugiej paczki

= 1.3.5 =
* Fix saving data to order meta for HPOS for all methods

= 1.3.4 =
* Fix saving data to order meta for HPOS

= 1.3.3 =
* Fix deprecated error, fix saving meta to order details

= 1.3.2 =
* Fix dispatch methods for Courier C2C method and COD amount for C2C COD

= 1.3.1 =
* New setting - default size for Courier C2C method

= 1.3.0 =
* Limit of order total in 5000 for COD methods
* Fix new block's Checkout when only one Inpost shipping method is enabled

= 1.2.9 =
* CSS fix
* Change enquene of "front-blocks.js" script 
* New setting for shipping methods - disable/enable "free shipping" notice 

= 1.2.8 =
* Fix conflict in scripts

= 1.2.7 =
* Integracja z Woo Blocks checkout page
* Additional settings in Debug section

= 1.2.6 =
* Fix validation error if parcel locker data is missed
* Change enquene of "inpost-geowidget.js" script
* Fix deprecated errors for PHP 8.2

= 1.2.5 =
* Fix map opening on plugin setting's page
* Autocomplete field "parcel size" for parcel locker shipments based on settings saved in product

= 1.2.4 =
* Some small fixs

= 1.2.3 =
* Fix integracja z HPOS
* Fix nr. Paczkomatu Divi Theme

= 1.2.2 =
* Integracja z HPOS

= 1.2.1 =
* Naprawiono: integracja z wtyczką Flexible Shipping

= 1.2.0 =
* Zapisywanie danych punktu przy Checkout do LocalStorage
  Naprawiono: podwójna ulica na etykiecie

= 1.1.9 =
* Możliwość zmiany statusu zamówienia w przypadku tworzenia paczki

= 1.1.8 =
* Funkcjonalność - ustawienia domyślnego wymiaru dla przesyłki kurierskiej

= 1.1.7 =
* Funkcjonalność - Kupony na dostawę

= 1.1.6 =
* Nowa metoda - InPost Paczka Ekonomiczna

= 1.1.5 =
* Nowa metoda - InPost Kurier C2C COD
	Nowe ustawienie uwzględniania (lub ignorowania) kuponów na kwotę bezpłatnej wysyłki

= 1.1.4 =
* Obsługa opcji klas wysyłkowych


= 1.1.3 =
* Naprawiono: style CSS
	podwójne ostrzeżenie na stronie kasy,
    podwójna wiadomość w e-mailu w przypadku darmowej wysyłki,
    błąd sposobu obliczania darmowej przesyłki przy użyciu kuponu

= 1.1.2 =
* Naprawiono: błąd w uzyskaniu wagi z danych produktu
	Zmiana sposobu obliczania darmowej przesyłki przy użyciu kuponu
	Zmiana logo

= 1.1.1 =
* Refaktoryzacja połączenia z API
	Zmiana tytułu i treści wiadomości e-mail z numerem przesyłki

= 1.1.0 =
* Naprawiono: błąd wyświetlania listy zamówień

= 1.0.9 =
* Naprawiono: błąd podczas pobierania listy metod wysyłki w niektórych sklepach

= 1.0.8 =
* Masowe tworzenie przesyłek
* Możliwość wyboru koloru przycisku do wywołania karty
* Naprawiono: podłączenie skryptów

= 1.0.7 =
* Integracja z wtyczką Flexible Shipping - usunięto ukrywania metod InPost w ustawieniach Woocommerce
* Naprawiono: kalkulacja ceny na podstawie wymiarów przy usuwaniu towaru z koszyka

= 1.0.6 =
* Integracja z wtyczką Flexible Shipping
* Zmiana funkcjonalności dozwolonych metod dostawy w ustawieniach produktu

= 1.0.5 =
* Naprawiono: stawki dla metod powielanych

= 1.0.4 =
* Dodana usługa 'Paczka w Weekend'.
* Naprawiono: przeładowania strony przy wyborze sposobu wysyłki

= 1.0.3 =
* Naprawiono: błąd walidacji pola na stronie kasy,
    błąd pobierania rozmiaru paczki na stronie szczegółów zamówienia,
    błąd z przyciskami na stronie 'Przesyłki'

= 1.0.2 =
* Zmieniono nazwę rozmiarów paczek w szczegółach zamówienia.

= 1.0.1 =
* naprawiono: linki w sekcji Moje konto.

= 1.0.0 =
* wstępne wydanie.
