# Kulturföreningen Gösta Johanssons Varv

Webbplats för Kulturföreningen Gösta Johanssons Varv – Gröna Varvet i Kungsviken, Orust.

## Om projektet

En modern, responsiv en-sideswebbplats byggd i ren HTML/CSS/JavaScript. Inga beroenden eller byggsteg krävs – filen `index.html` öppnas direkt i webbläsaren.

## Struktur

```
/
├── index.html      # Hela sajten i en fil
├── netlify.toml    # Netlify-konfiguration
└── README.md
```

## Lokal utveckling

Öppna `index.html` direkt i webbläsaren, eller använd en enkel lokal server:

```bash
npx serve .
# eller
python3 -m http.server 8000
```

## Driftsättning

Sajten är konfigurerad för Netlify. Koppla GitHub-repot till Netlify och välj:

- **Build command:** *(lämna tomt)*
- **Publish directory:** `.`

## Kontakt

info@gostasvarv.se  
www.gostasvarv.se
