<?php
// Shared helper utilities
if (!function_exists('rk_base_path')) {
	function rk_base_path(): string {
		return rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\') . '/';
	}
}

if (!function_exists('rk_normalize_banner_url')) {
	/**
	 * Normaliza una ruta de banner para funcionar aunque el sitio esté en un subdirectorio.
	 * Acepta URLs absolutas (http/https) y rutas relativas (con ../, ./ etc.).
	 */
	function rk_normalize_banner_url(?string $u, ?string $basePath = null): string {
		if (!$u) return '';
		if (preg_match('#^https?://#i', $u)) return $u; // absoluta
		$u = str_replace('\\', '/', $u);
		$u = preg_replace('#^\./#', '', $u);
		while (strpos($u, '../') === 0) { $u = substr($u, 3); }
		$u = ltrim($u, '/');
		$basePath = $basePath ?? rk_base_path();
		return rtrim($basePath, '/') . '/' . $u;
	}
}

if (!function_exists('rk_banner_exists')) {
	/**
	 * Verifica en filesystem si existe el archivo (solo para rutas internas no absolutas).
	 */
	function rk_banner_exists(?string $u): bool {
		if (!$u || preg_match('#^https?://#i', $u)) return true; // asumimos OK para externas
		$u = str_replace('\\', '/', $u);
		$u = preg_replace('#^\./#', '', $u);
		while (strpos($u, '../') === 0) { $u = substr($u, 3); }
		$u = ltrim($u, '/');
		$root = realpath(__DIR__ . '/..');
		if (!$root) return false;
		$full = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $u);
		return is_file($full);
	}
}

if (!function_exists('rk_banner_public_url')) {
	/**
	 * Devuelve la URL pública del banner (normalizada) y agrega ?v=mtime para cache busting si es local.
	 */
	function rk_banner_public_url(?string $u, ?string $basePath = null): string {
		if (!$u) return '';
		$raw = $u;
		$public = rk_normalize_banner_url($raw, $basePath);
		if (!preg_match('#^https?://#i', $raw)) {
			// Resolver mtime si existe
			$p = str_replace('\\','/',$raw);
			$p = preg_replace('#^\./#','',$p);
			while (strpos($p,'../')===0) { $p = substr($p,3); }
			$p = ltrim($p,'/');
			$root = realpath(__DIR__ . '/..');
			if ($root) {
				$full = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $p);
				if (is_file($full)) {
					$mt = @filemtime($full);
					if ($mt) {
						$sep = (strpos($public,'?')===false) ? '?' : '&';
						$public .= $sep . 'v=' . $mt;
					}
				}
			}
		}
		return $public;
	}
}
?>
