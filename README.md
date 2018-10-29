**Certificate Generator**

Dieses Projekt besteht aus Klassen zum Parsen von Schlüsselpaaren und zum Generieren von Certificate Signing Requests (CSRs).
Diese CSRs kann es zur SOAP-Schnittstelle der DFN-PKI hochladen und die dort signierten Zertifikate abholen.

Ausgangspunkt am RRZE ist eine Datenbank, in der alle von uns verwalteten Webauftritte mit den dazugehörigen Aliassen zu finden sind. Die Datenbank hat eine JSON-Schnittstelle, die die hier benötigten Daten zur Verfügung stellt, unter anderem eine vorgenerierte openssl.cnf für jeden VirtualHost.

In der Datei

    config/config.php

sind die Verzeichnisse konfiguriert, in denen diverse Dateitypen abgelegt werden:

    # Directory settings
    $config['dir']['base'] = DIR_BASE;
    $config['dir']['config'] = DIR_BASE . 'config/';
    $config['dir']['log'] = DIR_BASE . 'log/';
    $config['dir']['cnf'] = DIR_BASE . 'files/cnf/';
    $config['dir']['key'] = DIR_BASE . 'files/key/';
    $config['dir']['crt'] = DIR_BASE . 'files/crt/';
    $config['dir']['csr'] = DIR_BASE . 'files/csr/';
    $config['dir']['pdf'] = DIR_BASE . 'files/pdf/';
    $config['dir']['data'] = DIR_BASE . 'files/data/';
    $config['dir']['todo']['csr'] = DIR_BASE . 'files/todo/csr/';
    $config['dir']['todo']['cnf'] = DIR_BASE . 'files/todo/cnf/';
    $config['dir']['todo']['dfn'] = DIR_BASE . 'files/todo/dfn/';
    $config['dir']['backup'] = DIR_BASE . 'files/backup/';
    $config['dir']['lang'] = DIR_BASE . 'resources/language/';
    $config['dir']['currentkey'] = '/path/to/private-key-directory/';
    $config['dir']['currentcrt'] = '/path/to/public-key-directory/';
    $config['dir']['currentchain'] = '/path/to/chain-directory/';



Die Untergruppe

    $config['dir']['todo']
    
wird folgendermaßen bedient:

Zunächst wird (in diesem Fall von bin/fetch_cnf) ein Symlink in 

    $config['dir']['todo']['cnf'] 

auf eine Datei in 

    $config['dir']['cnf'] 

angelegt, also in etwa ein Link von

    files/todo/cnf/openssl.www.irgendwas.test.fau.de.cnf
    
auf

    files/cnf/openssl.www.irgendwas.test.fau.de.cnf
    
Sobald das erledigt ist, kann man mittels

    bin/push_csr -r yes/no

einen Certificate Signing Request aus der verlinkten openssl.cnf erzeugen - der Schalter "-r" (regenerate) gibt an, ob dabei ein neuer Private Key erzeugt werden soll oder der bisherige weiterverwendet.

Der erzeugte CSR wird in files/csr/ als www.irgendwas.test.fau.de.csr abgelegt und ein Symlink auf diese Datei mit dem Dateinamen files/todo/csr/www.irgendwas.test.fau.de.csr ezeugt.

Für alle Symlinks in files/todo/csr wird im folgenden versucht, sie via SOAP-Schnittstelle bei der DFN-PKI hochzuladen. Wenn das erfolgreich ist, passiert folgendes:

* Der Symlink wird gelöscht und die CSR-Metadeten in JSON-Format als files/todo/dfn/(CA-Request-Nr).json gespeichert. 
* Der Zertifikatsantrag wird heruntergeladen, unter files/pdf/(CA-Request-Nr)-www.irgendwas.test.fau.de.pdf abgelegt und gleichzeitig an eine zu konfigurierende E-Mail-Adresse verschickt.

Der Zertifikatsantrag muss nun ausgedruckt und unterschrieben zum örtlichen Teilnehmerservice der DFN-CA gebracht werden. Sobald das Zertifikat erstellt ist, kann es mittels 

    bin/pull_dfncrt
    
heruntergeladen werden - dabei wird das aktuelle Schlüsselpaar in files/backup verschoben und das neue Schlüsselpaar in die jeweiligen Ordner, also die unter 

    $config['dir']['currentkey'] = '/path/to/private-key-directory/';
    $config['dir']['currentcrt'] = '/path/to/public-key-directory/';

konfigurierten, abgelegt.