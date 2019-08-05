# Szenensteuerung
Die Szenensteuerung speichert Werte von verlinkten Variablen in Szenen und kann diese via Knopfdruck aus dem WebFront und mobilen Apps wieder aufrufen.  
Die zu schaltenden Variablen müssen dazu in der "Targets"-Kategorie als Link hinzugefügt werden.
Sind alle für eine Szene gewünschten Variablen verlinkt und auf den gewünschten Wert gesetzt, können diese mit dem "Speichern"-Knopf (im Webfront) der entsprechenden Szene hinzugefügt werden.
Nun kann die Szene mit dem entsprechenden "Ausführen"-Knopf jederzeit abgerufen werden. Die Variablen werden auf die zuvor gespeicherten Werte gesetzt.

### Inhaltverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Ermöglicht das Speichern und Ausführen von verlinkten Variablen über Szenen.
* Darstellung und Bedienung via WebFront und mobilen Apps
* JSON kodierte Speicherung von Szenendaten

### 2. Voraussetzungen

- IP-Symcon ab Version 4.x

### 3. Software-Installation

Über das Modul-Control folgende URL hinzufügen.  
`git://github.com/symcon/SymconMisc.git`  

### 4. Einrichten der Instanzen in IP-Symcon

- Unter "Instanz hinzufügen" ist das 'Szenensteuerung'-Modul unter dem Hersteller '(Sonstige)' aufgeführt.  

__Konfigurationsseite__:

Name   | Beschreibung
------ | ---------------------------------
Scenes | Anzahl der Szenen die zur Verfügung gestellt werden.

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

##### Statusvariablen
Die Szenen werden 1,2..n aufsteigend durchnummeriert.

Name      | Typ       | Beschreibung
--------- | --------- | ----------------
Targets   | Kategorie | Beinhaltet die verlinkten Variablen, deren Werte gespeichert und wieder aufgerufen werden sollen.
Scene     | Integer   | Zur Anzeige im WebFront und den mobilen Apps. Ruft "Speichern" oder "Ausführen" auf.
SceneData | String    | Speichert JSON kodierte Datensätze für die jeweilige Szene

##### Profile:

Name             | Typ
---------------- | ------- 
SZS.SceneControl | Integer


### 6. WebFront

Über das WebFront können die momentanen Werte der verlinkten Zielvariablen in einer Scene gespeichert werden.
Über "Ausführen" können bereits gespeicherte Scenen aufgerufen werden.

### 7. PHP-Befehlsreferenz

`boolean SZS_SaveScene(integer $InstanzID, integer $SceneNumber);`  
Speichert die Werte der verlinkten Variablen aus der Kategorie "Targets" in der Szene mit der Nummer $SceneNumber in dem Szenensteuerungsmodul mit der InstanzID $InstanzID.  
Die Funktion liefert keinerlei Rückgabewert.  
Beispiel:  
`SZS_SaveScene(12345, 1);`

`boolean SZS_CallScene(integer $InstanzID, integer $SceneNumber);`  
Ruft die in dem Szenensteuerungsmodul mit der InstanzID $InstanzID gespeicherten Werte der Szene mit der Nummer $SceneNumber auf und setzt die dazugehörigen Variablen.
Die Funktion liefert keinerlei Rückgabewert.  
Beispiel:  
`SZS_CallScene(12345, 1);`
