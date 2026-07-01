# BirdChirp

its a social media thing. post shit. follow people. php and mysql.

## do this first

1. `docker compose up -d`
2. go to http://localhost:8080
3. wizard does the rest

## after the wizard

- **delete setup.php** or bad things
- **make uploads/ and uploads/avatars/** or images break

## setup wizard

- db creds (leave password blank if u got none)
- turnstile / mailtrap / discord webhook (u dont NEED these but theyre nice)
- make ur admin account
- pick site settings

## env vars

| Var | What |
|---|---|
| DB_HOST | mysql host |
| DB_NAME | db name |
| DB_USER | db user |
| DB_PASS | db pass (leave empty if none) |
| TURNSTILE_SECRET | bot protection (skip if blank) |
| MAILTRAP_API_KEY | email verify (skip if blank) |
| DISCORD_WEBHOOK_URL | admin logs to discord (skip if blank) |

## manual

mysql db -> import database/schema.sql -> set env vars -> point apache/nginx at it -> make uploads/ and uploads/avatars/

## license

do whatever the fuck you want public license. do what u want idc.
