-- =====================================================================
-- Calculadora de Cielorraso PVC - esquema de base de datos (referencia)
--
-- NO es necesario ejecutar este archivo: el plugin crea y actualiza las
-- tablas automáticamente al activarse (dbDelta) y carga datos de ejemplo.
-- Reemplace "wp_" por el prefijo de su instalación si lo ejecuta a mano.
-- =====================================================================

-- Categorías de materiales y modo de selección:
--   all      = se calculan todos los materiales activos de la categoría
--   single   = el cliente elige uno (desplegable)
--   optional = el cliente elige uno o ninguno
CREATE TABLE wp_ccr_categories (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(190) NOT NULL,
  slug varchar(100) NOT NULL,
  description text NULL,
  selection_mode varchar(20) NOT NULL DEFAULT 'all',
  customer_label varchar(190) NOT NULL DEFAULT '',
  sort_order int(11) NOT NULL DEFAULT 0,
  active tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NULL DEFAULT NULL,
  updated_at datetime NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY slug (slug),
  KEY active_order (active, sort_order)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tipos de instalación (cada uno expone la variable inst_<slug> = 1/0).
CREATE TABLE wp_ccr_install_types (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(190) NOT NULL,
  slug varchar(100) NOT NULL,
  description text NULL,
  is_default tinyint(1) NOT NULL DEFAULT 0,
  sort_order int(11) NOT NULL DEFAULT 0,
  active tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NULL DEFAULT NULL,
  updated_at datetime NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY slug (slug)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Materiales / productos. "formula" calcula la cantidad base; luego se aplica
-- correction_factor, waste_pct (NULL = desperdicio general), min_qty y redondeo
-- (rounding + round_multiple). price es por cada "price_qty" unidades.
-- variants = JSON [{"label":"5 m","length":5,"width":0,"price":220}, ...]
-- install_types = IDs separados por coma (vacío = todos).
CREATE TABLE wp_ccr_materials (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  category_id bigint(20) unsigned NOT NULL DEFAULT 0,
  code varchar(64) NOT NULL,
  sku varchar(100) NOT NULL DEFAULT '',
  name varchar(190) NOT NULL,
  description text NULL,
  unit varchar(50) NOT NULL DEFAULT 'unidad',
  length decimal(12,4) NOT NULL DEFAULT 0,
  width decimal(12,4) NOT NULL DEFAULT 0,
  yield decimal(14,4) NOT NULL DEFAULT 0,
  price decimal(14,4) NOT NULL DEFAULT 0,
  price_qty decimal(12,4) NOT NULL DEFAULT 1,
  waste_pct decimal(7,3) NULL DEFAULT NULL,
  min_qty decimal(12,4) NOT NULL DEFAULT 0,
  correction_factor decimal(10,4) NOT NULL DEFAULT 1,
  rounding varchar(10) NOT NULL DEFAULT 'ceil',
  round_multiple decimal(12,4) NOT NULL DEFAULT 1,
  decimals tinyint(3) unsigned NOT NULL DEFAULT 0,
  formula text NULL,
  variants longtext NULL,
  install_types varchar(255) NOT NULL DEFAULT '',
  sort_order int(11) NOT NULL DEFAULT 0,
  active tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NULL DEFAULT NULL,
  updated_at datetime NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY code (code),
  KEY category_id (category_id),
  KEY active_order (active, sort_order)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reglas de cálculo: variables intermedias evaluadas en orden.
CREATE TABLE wp_ccr_variables (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  var_key varchar(64) NOT NULL,
  label varchar(190) NOT NULL DEFAULT '',
  formula text NULL,
  description text NULL,
  sort_order int(11) NOT NULL DEFAULT 0,
  active tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NULL DEFAULT NULL,
  updated_at datetime NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY var_key (var_key)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Leads (contactos). token = identificador de sesión para no pedir datos otra vez.
CREATE TABLE wp_ccr_leads (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  token char(32) NOT NULL,
  name varchar(190) NOT NULL DEFAULT '',
  company varchar(190) NOT NULL DEFAULT '',
  phone varchar(60) NOT NULL DEFAULT '',
  email varchar(190) NOT NULL DEFAULT '',
  consent tinyint(1) NOT NULL DEFAULT 0,
  source_url varchar(255) NOT NULL DEFAULT '',
  created_at datetime NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY token (token),
  KEY email (email),
  KEY created_at (created_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cálculos realizados por cada lead (entrada y resultado en JSON).
CREATE TABLE wp_ccr_calculations (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
  inputs longtext NULL,
  results longtext NULL,
  area decimal(12,4) NOT NULL DEFAULT 0,
  total decimal(16,4) NOT NULL DEFAULT 0,
  observations text NULL,
  created_at datetime NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY lead_id (lead_id),
  KEY created_at (created_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajustes generales: wp_options.option_name = 'ccr_settings' (array serializado).

-- Consultas útiles --------------------------------------------------------
-- Leads con su cantidad de cálculos:
-- SELECT l.*, COUNT(c.id) AS calculos FROM wp_ccr_leads l
--   LEFT JOIN wp_ccr_calculations c ON c.lead_id = l.id GROUP BY l.id ORDER BY l.created_at DESC;
-- Subir 10% todos los precios de una categoría:
-- UPDATE wp_ccr_materials SET price = price * 1.10 WHERE category_id = 1;
