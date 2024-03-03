[![CodeFactor](https://www.codefactor.io/repository/github/surcoufx83/kochbuch/badge)](https://www.codefactor.io/repository/github/surcoufx83/kochbuch)

# Kochbuch

Ziel dieses Projekts ist die Entwicklung eines selbstverwalteten digitalen Kochbuchs für die eigene Familie und den Freundeskreis. Die Benutzung soll wie bei anderen Anbietern einfach und auf allen Endgeräten möglich sein.
Es basiert auf einem Docker-Image mit Nginx, PHP, einem Angular-basierten Frontend und einer MariaDB Datenbank.

Das Kochbuch ist vorbereitet für moderne Technologien wie die Anbindung von Chat GPT um Rezepte aus Fotos zu extrahieren oder automatisch zu übersetzen.

## Getting started

In Docker muss ein neuer Container angelegt werden. Das Image lautet `surcouf/kochbuch:latest`. Zusätzlich müssen zwei Volumes eingebunden werden, eines für die Konfiguration und eines für die hochgeladenen Rezeptfotos.

Beispiel:

```sh
docker run -d \
           --name kochbuch \
           -v kochbuch-config:/config \
           -v kochbuch-media:/media \
           -p 8080:80 \
           kochbuch:latest
```
