# Calculadora de Cielorraso PVC — Plugin WordPress

Calculadora de materiales para cielorrasos de PVC, administrable al 100 % desde WordPress:
materiales, fórmulas, desperdicios, redondeos, cotización, leads y exportación (PDF / Excel / impresión).

- Shortcode: `[calculadora_cielorraso]` (atributos opcionales `title`, `subtitle`, `class`)
- Bloque Gutenberg: **Calculadora de cielorraso PVC**
- Widget nativo de Elementor: **Calculadora cielorraso PVC**
- Menú de administración: **Cálculos de Cielorraso**

Requisitos: WordPress 6.0+, PHP 7.4+, MySQL 5.7+ / MariaDB 10.3+. Sin dependencias externas (ni jQuery, ni CDNs, ni librerías PDF/Excel).

---

## 1. Análisis de la calculadora de referencia (CPR)

Se analizó el código JavaScript de `cpr.com.uy/calcular?pg=PVC_inicio` (`PVC_inicio_RSP.js`, funciones
`validarPVC`, `mejorLargoYDireccion`, `calcular`). Todo el cálculo ocurre en el navegador y los productos
están embebidos en campos ocultos con el formato `precio@...@nombre@largos@ancho@colores`.

### Entradas
| Dato | Uso |
|---|---|
| Lado X, Lado Y | Medidas del ambiente (0,20 – 100 m) |
| Cámara de aire | Largo de los colgantes (0,05 – 15 m) |
| Tablilla + largo (3/4/5/6/8 m) | Ancho 0,20 m (0,25 en ripados); al largo nominal se le suman 0,02 m |
| Dirección | A lo ancho / a lo largo / **Más económica** |
| Terminación | U, moldura nobre, moldura premium (6 m) |
| Aislante | Rollos de 18 / 14,4 / 15 / 20 m² |
| Estructura | Perfil para perimetrales, colgantes, principales y red + separaciones (1,20 / 1,40 / 0,60 m) |

### Lógica de cálculo identificada
1. **Láminas (optimización de recortes):** filas = ⌊lado⊥ / ancho⌋; láminas enteras por fila = ⌊lado∥ / largo⌋.
   El sobrante de cada fila se corta de láminas extra: de cada lámina salen ⌊largo / sobrante⌋ piezas.
   Si queda una franja de ancho parcial se agrega una fila más (reutilizando sobrantes si los hay).
2. **"Más económica":** prueba cada largo disponible × cada dirección y elige el menor costo.
3. **Perfiles por línea con empalme:** con solape de 0,30 m, piezas por línea = ⌊(L − 0,30) / (P − 0,30)⌋ y el
   faltante de cada línea se obtiene de piezas compartidas (⌊P / faltante⌋ faltantes por pieza).
4. **Líneas de estructura:** principales = ⌊(lado − 0,05) / 1,40⌋, red = ⌊(lado − 0,05) / 0,60⌋,
   colgantes por línea = ⌊lado / 1,20⌋.
5. **Perímetro:** piezas enteras por lado + 1 a 4 piezas extra según cómo se combinan los sobrantes.
6. **Terminación U:** ⌈perímetro / 6,02⌉. Moldura: + 4 esquineros + uniones.
7. **Fijaciones:** a losa = colgantes × 2; a pared = ⌈perímetro / 0,40⌉ + 4; tornillos T1 = colgantes × 6 +
   principales × (red + 2) + fijación U + red × filas de láminas + 50 de reserva; todo redondeado a múltiplos de 50
   y cotizado por cada 100.
8. **Aislante:** área × 1,05 dividido en rollos.

**Verificación:** para 4,80 × 3,60 m, CPR devuelve 18 láminas 5 m, 3 U, 11 montantes, 10 soleras,
100 fijaciones y 300 tornillos T1 = **8.624 $**. Los datos de ejemplo del plugin reproducen exactamente ese resultado
(con sentido "más económica" el motor elige la lámina de 5 m a lo largo, igual que CPR).

---

## 2. Arquitectura

La idea central: **el código no conoce ningún material ni fórmula**. El plugin es un *motor* que evalúa
reglas y fórmulas guardadas en la base de datos.

```
Cliente (formulario)                      Servidor (admin-ajax: ccr_calculate)
 largo, ancho, alto, tipo,     ──POST──►  1. Nonce + límite por IP + honeypot
 sentido, materiales elegidos,            2. (Leads activos) valida/crea lead
 observaciones                            3. CCR_Calculator
                                             ├─ contexto base (medidas, sentido, inst_*)
                                             ├─ datos de materiales (CODIGO_largo, sel_CODIGO, cat_*)
                                             ├─ Reglas de cálculo (variables, en orden)
                                             ├─ Fórmula de cada material → × factor → + desperdicio
                                             │   → mínimo → redondeo a múltiplo → precio
                                             └─ Auto: prueba sentido × variantes y elige menor costo
 Resultados, PDF, Excel, imprimir ◄─JSON─  4. Guarda el cálculo (si hay lead) y responde
```

### Motor de fórmulas (`CCR_Expression`)
Analizador sintáctico propio (descendente recursivo). **No usa `eval()`**: solo números, variables,
operadores `+ - * / % ^`, comparaciones, `&& || !`, `c ? a : b` y una lista blanca de funciones:
`si/if, min, max, abs, ceil, floor, round, sqrt, pow, multiplo_superior, multiplo_inferior, lineas,
piezas_lineales, piezas_perimetro, piezas_superficie`. `floor`/`ceil` toleran errores de coma flotante.

### Variables disponibles en las fórmulas
| Variable | Significado |
|---|---|
| `largo`, `ancho`, `alto`, `area`, `perimetro`, `lado_mayor`, `lado_menor` | Medidas |
| `lado_paralelo`, `lado_perpendicular`, `sentido_largo`, `sentido_ancho` | Según el sentido de colocación |
| `inst_<tipo>` | 1 si el tipo de instalación elegido es `<tipo>` |
| `m_largo`, `m_ancho`, `m_rend`, `m_precio` | Datos del propio material (o de su variante) |
| `<codigo>_largo/_ancho/_rend/_precio`, `sel_<codigo>` | Datos de cualquier material |
| `q_<codigo>`, `qbase_<codigo>` | Cantidad final / base de un material ya calculado |
| `cat_<categoria>_largo/_ancho/_rend`, `sel_cat_<cat>`, `q_cat_<cat>` | Material elegido en una categoría |
| Cualquier regla creada en *Reglas de cálculo* | p. ej. `sep_red`, `lineas_red`, `total_colgantes` |

### Estructura de archivos
```
calculadora-cielorraso-pvc/
├── calculadora-cielorraso-pvc.php      Cabecera del plugin, constantes, autoloader
├── uninstall.php                       Borrado opcional de datos
├── readme.txt / README.md
├── includes/
│   ├── class-ccr-plugin.php            Arranque de módulos
│   ├── class-ccr-activator.php         Tablas (dbDelta), migraciones, datos iniciales
│   ├── class-ccr-seeder.php            Catálogo y reglas de ejemplo (equivalentes a CPR)
│   ├── class-ccr-settings.php          Ajustes (esquema + sanitización)
│   ├── class-ccr-repository.php        Acceso a datos (prepare/insert/update, listas blancas)
│   ├── class-ccr-expression.php        Motor de fórmulas seguro
│   ├── class-ccr-expression-exception.php
│   ├── class-ccr-math.php              Funciones de obra (recortes, perímetro, líneas)
│   ├── class-ccr-calculator.php        Motor de cálculo + optimización
│   ├── class-ccr-ajax.php              Endpoint público + leads + rate limit
│   ├── class-ccr-shortcode.php         Shortcode y assets
│   ├── class-ccr-block.php             Bloque Gutenberg
│   ├── class-ccr-xlsx.php              Generador .xlsx sin dependencias
│   └── integrations/                   Widget de Elementor
├── admin/
│   ├── class-ccr-admin.php             Menú, panel, ayuda de fórmulas, prueba AJAX
│   ├── class-ccr-admin-crud.php        CRUD genérico (listar/crear/editar/duplicar/activar/borrar)
│   ├── class-ccr-admin-entities.php    Definición de Materiales, Categorías, Tipos, Reglas
│   ├── class-ccr-admin-formulas.php    Edición masiva de fórmulas y desperdicios
│   ├── class-ccr-admin-tester.php      Probador con todas las variables
│   ├── class-ccr-admin-leads.php       Leads + exportación CSV/Excel
│   ├── class-ccr-admin-settings.php    Ajustes
│   └── class-ccr-admin-tools.php       Exportar/importar JSON, restaurar ejemplo
├── templates/calculator.php            Formulario (sobrescribible desde el tema)
├── blocks/calculadora/                 block.json + editor (sin compilación)
├── assets/css/ccr-public.css, ccr-admin.css
├── assets/js/ccr-public.js             Formulario, resultados, diagrama SVG
├── assets/js/ccr-export.js             PDF, Excel e impresión (sin librerías)
├── assets/js/ccr-admin.js
└── sql/schema.sql                      Esquema de referencia
```

---

## 3. Base de datos

| Tabla | Contenido |
|---|---|
| `{prefix}ccr_categories` | Categorías y modo de selección (`all`, `single`, `optional`) |
| `{prefix}ccr_install_types` | Tipos de instalación |
| `{prefix}ccr_materials` | Materiales: nombre, descripción, unidad, largo, ancho, rendimiento, precio (por N unidades), % pérdida, mínimo, factor, redondeo, múltiplo, fórmula, variantes (JSON), tipos, estado |
| `{prefix}ccr_variables` | Reglas de cálculo (variables intermedias ordenadas) |
| `{prefix}ccr_leads` | Nombre, empresa, teléfono, email, consentimiento, origen |
| `{prefix}ccr_calculations` | Cada cálculo de un lead (entrada y resultado JSON) |
| `wp_options: ccr_settings` | Ajustes generales |

El plugin crea las tablas solo. `sql/schema.sql` queda como referencia.

---

## 4. Instalación

1. Comprimir la carpeta `calculadora-cielorraso-pvc` en un ZIP (o usar el ZIP entregado).
2. WordPress → **Plugins → Añadir nuevo → Subir plugin** → elegir el ZIP → **Instalar** → **Activar**.
3. Ir a **Cálculos de Cielorraso → Ajustes**: nombre de empresa, moneda, *Mostrar precios*, leads, colores.
4. Revisar **Materiales** y reemplazar los precios de ejemplo.
5. Insertar la calculadora:
   - Gutenberg: bloque *Calculadora de cielorraso PVC*.
   - Elementor: widget *Calculadora cielorraso PVC* (o widget *Shortcode* con `[calculadora_cielorraso]`).
   - Cualquier constructor / tema (Astra, GeneratePress, Hello Elementor): shortcode.
6. Usar **Probador** para verificar cualquier cambio de fórmulas.

> Si usa caché de página (WP Rocket, LiteSpeed, etc.), excluya la página de la calculadora o configure
> un tiempo de caché menor a 12 h para que el *nonce* no expire.

---

## 5. Guía rápida de administración

- **Materiales:** alta/edición/duplicado/activar/desactivar/eliminar. Cada material tiene su fórmula.
  Con **variantes** (p. ej. largos 3/4/5/6 m con precio propio) el cliente puede elegir el largo o dejarlo en automático.
- **Categorías:** *Todos* (se calculan todos), *Uno a elección* (desplegable) u *Opcional* (uno o ninguno).
- **Tipos de instalación:** cada material indica a qué tipos aplica; además existe `inst_<tipo>` para fórmulas.
- **Reglas de cálculo:** separaciones, solapes, cantidad de líneas… Una regla puede usar las anteriores.
- **Fórmulas y desperdicios:** desperdicio general y, en una sola tabla, fórmula, factor, % desperdicio,
  mínimo, redondeo, múltiplo, rendimiento y estado de cada material.
- **Probador:** ejecuta un cálculo mostrando cantidades intermedias, variables y advertencias.
- **Leads:** listado, detalle con cada cálculo, borrado, exportación CSV y Excel.
- **Herramientas:** exportar/importar la configuración completa (JSON) y restaurar el ejemplo.

Orden de aplicación por material: `fórmula → × factor → × (1 + desperdicio %) → mínimo → redondeo al múltiplo`.

Ejemplos:
```
area / m_rend                                      Rollos de aislante
perimetro / (m_largo + tolerancia_largo)           Perfiles perimetrales
piezas_superficie(lado_paralelo, lado_perpendicular, m_largo + tolerancia_largo, m_ancho)
si(inst_suspendido, total_colgantes * 2, 0)
tornillos_estructura + fijaciones_pared + tornillos_laminas + tornillos_reserva
```

---

## 6. Seguridad

- Nonces en todos los formularios, acciones y llamadas AJAX; comprobación de capacidad (`manage_options`, filtrable con `ccr_capability`).
- Sanitización por tipo de campo; fórmulas filtradas a caracteres permitidos y validadas sintácticamente.
- Consultas con `$wpdb->prepare()`, `insert()`, `update()`; nombres de tabla/columna/orden por lista blanca.
- Escapado de salida (`esc_html`, `esc_attr`, `esc_url`, `esc_textarea`, `wp_kses`); en el front todo se inserta con `textContent`.
- Sin `eval()`: evaluador propio de expresiones.
- Las fórmulas y (con *Mostrar precios* desactivado) los precios nunca se envían al navegador.
- Honeypot + límite de 60 cálculos / 10 min por IP (hash, no se guarda la IP); protección contra inyección de fórmulas en CSV.
- Con leads activos el servidor exige datos válidos antes de devolver resultados.

---

## 7. Extensibilidad

- Plantilla sobrescribible: `wp-content/themes/SU-TEMA/calculadora-cielorraso/calculator.php`.
- Filtros/acciones: `ccr_capability`, `ccr_rate_limit`, `ccr_lead_created` (id, datos).
- JS: `window.CCRInit()` re-inicializa calculadoras cargadas dinámicamente; `window.CCRExport` expone PDF/Excel/impresión.

## 8. Mejoras futuras sugeridas

1. Precios sincronizados con WooCommerce (vincular material ↔ producto) y botón "Agregar al carrito".
2. Varios ambientes en un mismo presupuesto (lista de habitaciones) y ambientes en L / polígonos.
3. Plano interactivo como el de CPR (luces, placas, bloques) y vista de estructura.
4. Envío del presupuesto por email al cliente con el PDF adjunto (generado en servidor).
5. Listas de precios múltiples (minorista / mayorista / USD) y cotización de moneda.
6. Historial de versiones de fórmulas con reversión y *diff*.
7. Integración con CRM (HubSpot, Pipedrive, RD Station) y webhooks por lead.
8. reCAPTCHA / Turnstile opcional y doble opt-in.
9. Estadísticas (m² cotizados, materiales más pedidos, conversión).
10. Traducción completa (archivo .pot) y multimoneda por idioma.
11. REST API pública documentada para apps móviles o POS.
12. Validación de compatibilidad de colores entre lámina y terminación (como hace CPR).
