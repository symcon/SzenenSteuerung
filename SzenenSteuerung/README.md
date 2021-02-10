# Szenensteuerung
Die Szenensteuerung speichert Werte von in einer Liste gespeicherten Variablen in Szenen und kann diese via Knopfdruck aus dem WebFront und mobilen Apps wieder aufrufen.  
Die zu schaltenden Variablen müssen dazu in der Instanzkonfiguration der Liste "Variablen" hinzugefügt werden.
Sind alle für eine Szene gewünschten Variablen hinzugefügt und auf den gewünschten Wert gesetzt, können diese mit dem "Speichern"-Knopf (im Webfront) der entsprechenden Szene hinzugefügt werden.
Nun kann die Szene mit dem entsprechenden "Ausführen"-Knopf jederzeit abgerufen werden. Die Variablen werden auf die zuvor gespeicherten Werte gesetzt.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Ermöglicht das Speichern und Aufrufen von in einer Liste gespeicherten Variablen über Szenen.
* Die aktuell aktive Szene wird angezeigt
* Darstellung und Bedienung via WebFront und mobilen Apps
* Wenn eine Variable ausgetauscht wird, bleiben bereits erstellte Szenen erhalten und schalten das neue Gerät

### 2. Voraussetzungen

- IP-Symcon ab Version 5.0

### 3. Software-Installation

* Über den Module Store das Modul Szenen-Steuerung installieren.
* Alternativ über das Module Control folgende URL hinzufügen:
`https://github.com/symcon/SzenenSteuerung`

### 4. Einrichten der Instanzen in IP-Symcon

- Unter "Instanz hinzufügen" kann das 'Szenensteuerung'-Modul mithilfe des Schnellfilters gefunden werden.
    - Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)
__Konfigurationsseite__:

Name      | Beschreibung
--------- | ---------------------------------
Szenen    | Anzahl der Szenen die zur Verfügung gestellt werden.
Variablen | Liste mit den zu schaltenden Variablen.

### 5. Statusvariablen und Profile

Die Statusvariablen werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

##### Statusvariablen
Die Szenen werden 1,2..n aufsteigend durchnummeriert.

Name         | Typ       | Beschreibung
------------ | --------- | ----------------
Aktive Szene | String    | Zeigt den Namen der gerade Aktiven Szene an. Wenn die Werte der Variablen einer Szene zugeornet werden können wird diese ebenfalls angezeigt. Entsprechen die Werte der Variablen keiner Szene wird 'Unbekann' angezeigt
Szene 1..n   | Integer   | Zur Anzeige im WebFront und den mobilen Apps. Ruft "Speichern" oder "Aufrufen" auf.


##### Profile:

Name             | Typ
---------------- | ------- 
SZS.SceneControl | Integer


### 6. WebFront

Über das WebFront können die momentanen Werte der gelisteten Zielvariablen in einer Scene gespeichert werden.
Über "Ausführen" können bereits gespeicherte Scenen aufgerufen werden.

### 7. PHP-Befehlsreferenz

`boolean SZS_SaveScene(integer $InstanzID, integer $SceneNumber);`  
Speichert die Werte der in der Liste vorhandenen Variablen in der entsprechenden Szene.  
Die Funktion liefert keinerlei Rückgabewert.  
Beispiel:  
`SZS_SaveScene(12345, 1);`

`boolean SZS_CallScene(integer $InstanzID, integer $SceneNumber);`  
Ruft die in dem Szenensteuerungsmodul mit der InstanzID $InstanzID gespeicherten Werte der Szene mit der Nummer $SceneNumber auf und setzt die dazugehörigen Variablen.
Die Funktion liefert keinerlei Rückgabewert.  
Beispiel:  
`SZS_CallScene(12345, 1);`

`integer SZS_GetActiveScene(integer $InstanzID);`
Gibt die Nummer der Szene zurück, welche gerade aktiv ist. Wenn eine unbekannte Szene aktiv ist wird 0 zurückgegeben.
Beispiel:
`SZS_GetActiveScene(12345);`


