# TutorLMS Proctor (custom)

Plugin WordPress personalizzato per proctoring su **TutorLMS**.

## Funzionalità
- Attivazione proctor **per corso** (non per singolo quiz)
- **Pre-flight check** mostrato solo se l’utente non ha ancora alcun tentativo quiz nel corso.
- Controllo **tab switch / perdita focus** con soglia configurabile.
- Al superamento soglia: **invalidazione** e richiesta di **autosubmit del quiz con 0 risposte**.

## Sviluppo
- PHP: `includes/`
- Admin settings: `includes/Admin/`
- Frontend JS: `assets/js/`
- CSS: `assets/css/`

## Installazione
1. Copia la cartella del plugin in `wp-content/plugins/tutorlms-proctor-custom/` oppure zip e installa da WP.
2. Attiva il plugin.
3. Vai su **Impostazioni → TutorLMS Proctor** e configura corsi e soglie.
