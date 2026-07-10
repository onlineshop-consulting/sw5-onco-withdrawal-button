# Shopware 5 Widerrufsbutton Plugin

Widerrufs-Button und Online-Widerrufsformular gemäß EU-Richtlinie 2023/2673.

## Features

- Erstellt bei Installation ein vorkonfiguriertes Widerrufsformular (DE + EN)
- Platziert Widerrufs-Buttons an konfigurierbaren Positionen (Konto-Menü, Footer, Bestellübersicht)
- Unterstützt individuelle Platzierung per CSS-Selektor
- Füllt das Formular automatisch mit Bestelldaten vor
- Sendet dem Kunden eine Bestätigungskopie der Widerrufserklärung per E-Mail

## Installation

1. ZIP herunterladen: https://github.com/onlineshop-consulting/sw5-onco-withdrawal-button/releases
2. Im Shopware-Backend unter **Einstellungen > Plugin-Manager** installieren und aktivieren
3. Cache leeren und Theme neu kompilieren

## Konfiguration
Die Konfiguration wird in diesem Video erklärt:
https://onlineshop.consulting/videos/plugins/sw5/onco-withdrawal.mp4

Bei Shopware 5.4 gibt es eine kleine Ausnahme bei der Konfiguration:
https://onlineshop.consulting/videos/plugins/sw5/onco-withdrawal-sw54.mp4

## Kompatibilität
PHP 5.6+
Shopware 5.4.0+

## Zeitangabe bei "Zeitpunkt des Widerrufs"

In Shopware 5 sind Zeitangaben in Formular-Templates leider immer im 12h-Format. Das betrifft auch den "Zeitpunkt des Widerrufs" in diesem Plugin. Um die Zeit im 24h-Format anzuzeigen, kann diese einfache Änderung direkt in Shopware gemacht werden:

In der Datei engine/Shopware/Controllers/Frontend/Forms.php suche:

`date('d.m.Y h:i:s')`

und ersetze mit:

`date('d.m.Y H:i:s')`

(also einfach das "h" in "H" ändern)