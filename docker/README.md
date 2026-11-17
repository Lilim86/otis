# Base de datos en Docker

La base `otis` corre en un contenedor de PostgreSQL 18. Laravel (`backend/.env`) se conecta a ella por el puerto **5433**.

```
otis/
├── compose.yaml                  ← qué contenedores hay y cómo se configuran
├── .env                          ← usuario y clave de la base (NO se sube a git)
├── .env.example                  ← plantilla del .env
└── docker/
    ├── README.md                 ← esta guía
    └── postgres/
        ├── init/                 ← scripts .sql que corren solo la primera vez que se crea la base
        └── respaldos/            ← respaldos .dump (NO se suben a git: tienen datos reales)
```

| Contenedor     | Qué es                         | Dónde                               |
|----------------|--------------------------------|-------------------------------------|
| `otis-db`      | PostgreSQL 18 con la base      | `127.0.0.1:5433`                    |
| `otis-adminer` | Página web para ver la base    | http://127.0.0.1:8080               |

Los datos viven en el volumen de Docker `otis_pgdata`: apagar o borrar los contenedores **no** borra los datos.

## Comandos del día a día (desde la carpeta `otis/`)

```bash
docker compose up -d          # encender la base (y Adminer)
docker compose ps             # ver si están corriendo y "healthy"
docker compose logs -f db     # ver lo que dice PostgreSQL (Ctrl+C para salir)
docker compose stop           # apagar sin borrar nada
docker compose down           # apagar y quitar los contenedores (los datos se quedan en el volumen)
```

Con `restart: unless-stopped` los contenedores se encienden solos al prender la PC, salvo que los hayas apagado tú.

## Mirar los datos

**Con Adminer (navegador):** abre http://127.0.0.1:8080 y llena:
- Sistema: `PostgreSQL`
- Servidor: `db`
- Usuario y contraseña: los de `DB_USERNAME` / `DB_PASSWORD` del `.env`
- Base de datos: `otis`

**Con la consola de PostgreSQL dentro del contenedor:**

```bash
docker compose exec db psql -U postgres -d otis
#   \dt                     lista las tablas
#   \d cobranzas            columnas de una tabla
#   select count(*) from users;
#   \q                      salir
```

## Respaldos

```bash
# Sacar un respaldo (queda en docker/postgres/respaldos/)
docker compose exec db pg_dump -U postgres -d otis -Fc -f /respaldos/otis-$(date +%Y%m%d-%H%M).dump

# Cargar un respaldo (BORRA lo que haya y lo reemplaza por el respaldo)
docker compose exec db pg_restore -U postgres -d otis --clean --if-exists --no-owner /respaldos/ARCHIVO.dump
```

## Volver al PostgreSQL instalado en la PC

En `backend/.env` cambia `DB_PORT=5433` por `DB_PORT=5432` y corre `php artisan config:clear`.
(La copia del `.env` de antes del cambio quedó en `backend/.env.backup`.)
