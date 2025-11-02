# Limpieza de Scripts Temporales (2025-09-24)

Se eliminaron / neutralizaron scripts de mantenimiento, diagnóstico y siembra que no deben estar en producción.

## Scripts neutralizados (responden 410 / eliminados)
- `add_specific_admin.php` (ya neutralizado previamente)
- `repair_admins.php`
- `diagnose_admins.php`
- `repair_categories.php`
- `create_admin_and_seed.php`
- `seed_admin_products.php`

## Motivo
Reducir superficie de ataque y evitar recreaciones accidentales de tablas o generación masiva de datos en entorno vivo.

## Sustituciones
- Creación de administradores ahora sólo vía formulario seguro en `settings.php`.
- Mantenimiento de imágenes huérfanas via acción POST con CSRF también en `settings.php`.

## Notas
Si se requiere nuevamente alguna función de diagnóstico, implementar nueva versión limitada detrás de autenticación fuerte y revisar logs.

---
Este archivo puede eliminarse después de revisar los cambios. 
