# devconjorge.com — tema WordPress

Tema "terminal líquida" de @devconjorge + plugin de bitácora automática, listo para Coolify.

## Qué trae

- **Tema `devconjorge`** (`wp-content/themes/devconjorge`)
  - Home: hero con titular animado, split-flap, dock de redes, teléfono con el último Short.
  - **Contador de seguidores** por red (YouTube, TikTok, Instagram, Facebook, WhatsApp, Twitch) con total animado. Cifras en *Apariencia → Personalizar → DevConJorge → Redes y seguidores*. YouTube se actualiza solo si pones una API key de YouTube Data v3.
  - **Míralo en 60 s** como app: feed vertical con scroll-snap, autoplay al entrar, toque para pausar, doble toque = corazón, sonido, compartir, barra de progreso real, flechas, teclado (↑ ↓ j k espacio m) y rueda del mouse. Lee los últimos videos del canal por RSS (sin API key).
  - Bitácora (página de entradas) con filtros por categoría y artículo con el Short embebido y firma.
  - Formulario de recursos que guarda correos en *Suscriptores* (exportables a CSV).
  - Al activarlo crea las páginas Inicio y Bitácora y deja los enlaces permanentes en `/%postname%/`.
- **Plugin `dcj-autoblog`** (`wp-content/plugins/dcj-autoblog`)
  - Cada hora lee el RSS del canal y crea un artículo por video nuevo, firmado por el autor elegido, con la miniatura como imagen destacada.
  - Con API key de Claude redacta el artículo con la voz del canal a partir del título y la descripción. Sin key, usa la descripción.
  - Por defecto crea **borradores**. Ajustes en *Ajustes → Bitácora automática*.

## Probar en local

```bash
docker compose -f docker-compose.yml -f docker-compose.local.yml up --build
```

Abre http://localhost:8080, instala WordPress, activa el tema **DevConJorge** y el plugin **Bitácora automática**.

## Desplegar en Coolify

1. New Resource → Public/Private Repository → este repo → Build Pack **Docker Compose**.
2. Coolify genera `SERVICE_USER_WORDPRESS`, `SERVICE_PASSWORD_WORDPRESS`, `SERVICE_PASSWORD_ROOT` y el dominio (`SERVICE_FQDN_WORDPRESS_80`). Asigna devconjorge.com al servicio `wordpress`.
3. Deploy. Cada push redespliega con la versión nueva del tema y el plugin (el `Dockerfile` los copia al volumen al arrancar; uploads y otros plugins no se tocan).

**Ojo con el redirect `/wp`**: hoy devconjorge.com/wp apunta al canal de WhatsApp desde GHL. Si WordPress pasa a ser el dominio, crea ese redirect en WordPress (plugin Redirection o regla en Coolify/Traefik) antes de cambiar el DNS.

## Después de instalar

- Usuarios → tu perfil → "Mostrar públicamente como": **Ing. Jorge Ochoa**.
- Personalizar → DevConJorge: confirma enlaces (Twitch se asumió como twitch.tv/devconjorge), actualiza cifras de TikTok, Instagram y Facebook (son de mayo 2026), correo de marcas, media kit y la foto.
- Ajustes → Bitácora automática: API key de Claude (opcional) y "Revisar el canal ahora".

## Pendiente

- Probarlo en un navegador real (no se pudo levantar WordPress en el entorno donde se escribió).
- Verificar la autoplay del feed en iPhone (Safari exige `mute=1`, ya está por defecto).
