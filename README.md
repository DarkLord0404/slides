# Koqoi Slides

Plataforma web para crear presentaciones interactivas y recibir respuestas de una audiencia en vivo.

## MVP incluido

- Registro e inicio de sesión para creadores.
- Presentaciones privadas por propietario.
- Editor de diapositivas con título y contenido.
- Actividades de opción múltiple, texto abierto, nube de palabras y verdadero/falso.
- Sesiones en vivo con código de seis dígitos.
- Participación desde el teléfono sin crear cuenta.
- Respuestas persistentes y panel de resultados actualizado automáticamente.
- Configuración Nginx para `slides.koqoi.com`.

## Instalación

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Configura PostgreSQL en `.env` para producción. El directorio `public` debe ser la raíz del virtual host.

## Pruebas

```bash
php artisan test
```

## Próximos módulos

- Importación de PowerPoint mediante conversión asíncrona a fondos WebP.
- Laravel Reverb para presencia y actualizaciones WebSocket.
- Control remoto de diapositiva activa.
- Exportaciones CSV/PDF y moderación de contenido.
