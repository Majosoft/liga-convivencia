<?php
if (!defined('ABSPATH')) exit;

/**
 * Instalación y actualización del esquema.
 *
 * Crea las tablas del plugin (dbDelta) y aplica las migraciones de versiones
 * anteriores. Todas son idempotentes: se pueden ejecutar varias veces sin
 * efecto. Se lanza al activar el plugin y, sin reactivarlo, cada vez que
 * cambia LC_VERSION (ver liga-creus.php).
 *
 * Documentación: docs/modulos/configuracion.md
 */
class LC_Activator {
  public static function activate(){
    global $wpdb; require_once ABSPATH.'wp-admin/includes/upgrade.php';
    $cc = $wpdb->get_charset_collate(); $p = $wpdb->prefix;

    self::create_tables($wpdb, $cc, $p);
    self::migrate_deportes_unique_key($wpdb, $p);
    self::migrate_tiempo_aprendizaje_legacy($wpdb, $p);
    self::migrate_partes_legacy($wpdb, $p);
    self::migrate_partes_severidad($wpdb, $p);
    self::migrate_partes_a_alumnos($wpdb, $p);
    self::migrate_extraordinarias_legacy($wpdb, $p);
    self::migrate_emails_to_usuarios();
    self::migrate_usuarios_sin_pin();

    // 1.0: horario del profesorado y gomets por hora (ver LC_Horario). La
    // primera vez carga el horario y los profesores de 2026-27 (data/).
    LC_Horario::create_tables($wpdb, $cc, $p);
    LC_Horario::seed();

    // 1.2: informes de IA para el administrador.
    LC_Informes_IA::create_tables($wpdb, $cc);
  }

  // Migra la antigua lista de "emails autorizados" (opción suelta lc_allowed_emails)
  // a la lista de usuarios de Acceso > Usuarios, que es donde vive ahora. El acceso
  // sigue siendo por email, así que quien lo tenía lo conserva.
  private static function migrate_emails_to_usuarios(){
    $legacy = get_option(LC_Auth::OPT_KEY_LEGACY, '');
    if ($legacy === '' || $legacy === false) return;

    $lines = array_filter(array_map('trim', preg_split('/\r?\n/', (string)$legacy)));
    $emails = array_unique(array_map('strtolower', array_map('sanitize_email', $lines)));
    if (empty($emails)) return;

    $users = LC_Auth::get_users();
    $known = array_map(function($u){ return strtolower($u['email'] ?? ''); }, $users);
    foreach ($emails as $email) {
      if ($email === '' || in_array($email, $known, true)) continue;
      $users[] = ['email' => $email, 'creado' => current_time('mysql')];
      $known[] = $email;
    }
    update_option(LC_Auth::OPT_USERS, $users);
    delete_option(LC_Auth::OPT_KEY_LEGACY);
  }

  // Al volver al acceso solo por email, los hashes de PIN guardados en lc_usuarios
  // dejan de leerse: se eliminan para no conservar credenciales que ya no sirven
  // para nada. Esta migración corre sola al subir LC_VERSION (ver liga-creus.php).
  private static function migrate_usuarios_sin_pin(){
    $users = get_option(LC_Auth::OPT_USERS, null);
    if (!is_array($users)) return;

    $changed = false;
    foreach ($users as &$u) {
      if (is_array($u) && array_key_exists('pin_hash', $u)) {
        unset($u['pin_hash']);
        $changed = true;
      }
    }
    unset($u);

    if ($changed) update_option(LC_Auth::OPT_USERS, array_values($users));
  }

  // Elimina la clave única uniq_match (deporte, semana, equipo_a, equipo_b) de
  // las instalaciones que ya la tuvieran. Impedía registrar más de un partido
  // entre las mismas dos clases en la misma semana, que es exactamente lo que
  // hace falta en el modo "alumno A vs alumno B". La deduplicación de los
  // partidos entre clases sigue haciéndose en PHP (ver rest-deportes.php), así
  // que no se pierde ninguna protección real. dbDelta no sabe borrar índices,
  // por eso se hace aquí a mano.
  private static function migrate_deportes_unique_key($wpdb, $p){
    $table = "{$p}lc_deportes";
    $existe = $wpdb->get_var("SHOW INDEX FROM {$table} WHERE Key_name = 'uniq_match'");
    if (!$existe) return;
    $wpdb->query("ALTER TABLE {$table} DROP INDEX uniq_match");
  }

  // Repara lc_tiempo_aprendizaje en las instalaciones que vienen de la versión
  // antigua (≤ 0.5), que guardaba el mes como texto en "mes" ("2026-10") con la
  // clave única uniq_mes_clase (mes, clase). dbDelta añadió iso_year/iso_month
  // pero no sabe quitar índices ni columnas, así que quedaban dos problemas:
  // - Las altas nuevas no rellenan "mes" (NOT NULL): según el modo SQL, o
  //   fallaban o entraban con mes = '', y entonces uniq_mes_clase solo admitía
  //   una fila por clase y cada mes nuevo pisaba el anterior.
  // - La clave buena clase_mes no llegaba a crearse: las filas antiguas tenían
  //   iso_year/iso_month = 0 y chocaban entre sí.
  // Aquí se trasladan los meses antiguos a iso_year/iso_month, se quitan los
  // índices viejos, se deja "mes" opcional, se eliminan duplicados (gana la
  // fila más reciente) y se crea clase_mes si falta. Es idempotente.
  private static function migrate_tiempo_aprendizaje_legacy($wpdb, $p){
    $table = "{$p}lc_tiempo_aprendizaje";
    if (!$wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'mes'")) return;

    $wpdb->query("UPDATE {$table}
      SET iso_year = CAST(LEFT(mes, 4) AS UNSIGNED), iso_month = CAST(SUBSTRING(mes, 6, 2) AS UNSIGNED)
      WHERE (iso_year = 0 OR iso_month = 0) AND mes REGEXP '^[0-9]{4}-[0-9]{2}$'");

    foreach (['uniq_mes_clase', 'idx_mes'] as $idx) {
      if ($wpdb->get_var($wpdb->prepare("SHOW INDEX FROM {$table} WHERE Key_name = %s", $idx))) {
        $wpdb->query("ALTER TABLE {$table} DROP INDEX {$idx}");
      }
    }
    $wpdb->query("ALTER TABLE {$table} MODIFY mes VARCHAR(7) NULL DEFAULT NULL");

    // Filas que no se pudieron fechar: no cuentan en ninguna clasificación.
    $wpdb->query("DELETE FROM {$table} WHERE iso_year = 0 OR iso_month = 0");

    $wpdb->query("DELETE t1 FROM {$table} t1
      JOIN {$table} t2 ON t1.clase = t2.clase AND t1.iso_year = t2.iso_year
        AND t1.iso_month = t2.iso_month AND t1.id < t2.id");

    if (!$wpdb->get_var("SHOW INDEX FROM {$table} WHERE Key_name = 'clase_mes'")) {
      $wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY clase_mes (clase, iso_year, iso_month)");
    }
  }

  // Los partes registrados con el modelo antiguo (contadores por clase y semana
  // en lc_partes) dejaron de contar en la Clasificación al pasar a
  // lc_partes_alumnos, que es lo único que se lee ahora. Se trasladan como
  // partes "a toda la clase" (alumno_id = 0), uno por cada unidad del contador.
  // Solo las semanas de una clase que aún no tengan ningún parte en la tabla
  // nueva: así no se duplica nada si se vuelve a ejecutar ni si alguien ya los
  // había vuelto a meter a mano.
  private static function migrate_partes_a_alumnos($wpdb, $p){
    $old = "{$p}lc_partes";
    $new = "{$p}lc_partes_alumnos";
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $old)) !== $old) return;

    $filas = $wpdb->get_results(
      "SELECT o.clase, o.iso_year, o.iso_week, o.partes_positivos, o.partes_leves, o.partes_graves, o.created_by
       FROM {$old} o
       WHERE (o.partes_positivos > 0 OR o.partes_leves > 0 OR o.partes_graves > 0)
         AND NOT EXISTS (SELECT 1 FROM {$new} n
           WHERE n.clase = o.clase AND n.iso_year = o.iso_year AND n.iso_week = o.iso_week)",
      ARRAY_A
    );
    foreach ((array) $filas as $f) {
      foreach (['positivo' => 'partes_positivos', 'leve' => 'partes_leves', 'grave' => 'partes_graves'] as $tipo => $col) {
        for ($i = 0; $i < (int) $f[$col]; $i++) {
          $wpdb->insert($new, [
            'alumno_id'  => 0,
            'clase'      => $f['clase'],
            'iso_year'   => (int) $f['iso_year'],
            'iso_week'   => (int) $f['iso_week'],
            'tipo'       => $tipo,
            'notas'      => 'Migrado del registro semanal anterior',
            'created_by' => $f['created_by'],
          ], ['%d', '%s', '%d', '%d', '%s', '%s', '%s']);
        }
      }
    }
  }

  // lc_extraordinarias de la versión antigua (≤ 0.5) guardaba el nombre de la
  // actividad en "actividad" (NOT NULL, sin valor por defecto) y permitía
  // semana NULL. La versión actual usa "nombre" y no rellena "actividad", así
  // que cada alta nueva fallaba o entraba a medias, y las actividades antiguas
  // aparecían sin nombre en el panel. Se copia el nombre, se deja "actividad"
  // opcional y se crea la clave única si dbDelta no pudo. Idempotente.
  private static function migrate_extraordinarias_legacy($wpdb, $p){
    $table = "{$p}lc_extraordinarias";
    if (!$wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'actividad'")) return;

    $wpdb->query("UPDATE {$table} SET nombre = actividad WHERE (nombre = '' OR nombre IS NULL) AND actividad <> ''");
    $wpdb->query("ALTER TABLE {$table} MODIFY actividad VARCHAR(100) NULL DEFAULT NULL");

    // Una clase repetida en la misma actividad y semana impediría crear la
    // clave única: se queda la fila más reciente.
    $wpdb->query("DELETE t1 FROM {$table} t1
      JOIN {$table} t2 ON t1.nombre = t2.nombre AND t1.iso_year = t2.iso_year
        AND t1.iso_week = t2.iso_week AND t1.clase = t2.clase AND t1.id < t2.id");

    if (!$wpdb->get_var("SHOW INDEX FROM {$table} WHERE Key_name = 'nombre_semana_clase'")) {
      $wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY nombre_semana_clase (nombre(100), iso_year, iso_week, clase)");
    }
  }

  private static function create_tables($wpdb, $cc, $p){

    $sql1 = "CREATE TABLE {$p}lc_semaforo (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      clase VARCHAR(50) NOT NULL,
      iso_year SMALLINT NOT NULL,
      iso_week TINYINT NOT NULL,
      gomets_verdes TINYINT DEFAULT 0,
      gomets_amarillos TINYINT DEFAULT 0,
      gomets_rojos TINYINT DEFAULT 0,
      total INT DEFAULT 0,
      notas TEXT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      created_by VARCHAR(190) NULL,
      PRIMARY KEY (id),
      UNIQUE KEY uniq_sema (clase, iso_year, iso_week)
    ) $cc;";

    $sql2 = "CREATE TABLE {$p}lc_deportes (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      deporte VARCHAR(30) NOT NULL,
      iso_year SMALLINT NOT NULL,
      iso_week TINYINT NOT NULL,
      equipo_a VARCHAR(50) NOT NULL,
      equipo_b VARCHAR(50) NOT NULL,
      marcador_a INT DEFAULT 0,
      marcador_b INT DEFAULT 0,
      pts_a TINYINT DEFAULT 0,
      pts_b TINYINT DEFAULT 0,
      alumno_a BIGINT UNSIGNED NOT NULL DEFAULT 0,
      alumno_b BIGINT UNSIGNED NOT NULL DEFAULT 0,
      participantes TEXT NULL,
      notas TEXT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      created_by VARCHAR(190) NULL,
      PRIMARY KEY (id),
      KEY idx_match (deporte, iso_year, iso_week)
    ) $cc;";

    $sql3 = "CREATE TABLE {$p}lc_partes (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      clase VARCHAR(50) NOT NULL,
      iso_year SMALLINT NOT NULL,
      iso_week TINYINT NOT NULL,
      partes_positivos INT DEFAULT 0,
      partes_leves INT DEFAULT 0,
      partes_graves INT DEFAULT 0,
      notas TEXT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      created_by VARCHAR(190) NULL,
      PRIMARY KEY (id),
      UNIQUE KEY uniq_partes (clase, iso_year, iso_week)
    ) $cc;";

    $sql4 = "CREATE TABLE {$p}lc_biblioteca (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      actividad VARCHAR(30) NOT NULL,
      iso_year SMALLINT NOT NULL,
      iso_week TINYINT NOT NULL,
      participantes TEXT NOT NULL,
      puntos TINYINT DEFAULT 0,
      clase_a VARCHAR(50) NULL,
      clase_b VARCHAR(50) NULL,
      alumno_a BIGINT UNSIGNED NOT NULL DEFAULT 0,
      alumno_b BIGINT UNSIGNED NOT NULL DEFAULT 0,
      ganador VARCHAR(20) NULL,
      notas TEXT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      created_by VARCHAR(190) NULL,
      PRIMARY KEY (id),
      KEY idx_actividad_week (actividad, iso_year, iso_week)
    ) $cc;";

    $sql5 = "CREATE TABLE {$p}lc_extraordinarias (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      nombre VARCHAR(255) NOT NULL,
      iso_year SMALLINT NOT NULL,
      iso_week TINYINT NOT NULL,
      clase VARCHAR(100) NOT NULL,
      puntos INT NOT NULL DEFAULT 0,
      notas TEXT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      created_by VARCHAR(190) NULL,
      PRIMARY KEY (id),
      UNIQUE KEY nombre_semana_clase (nombre(100), iso_year, iso_week, clase)
    ) $cc;";

    $sql6 = "CREATE TABLE {$p}lc_tiempo_aprendizaje (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      clase VARCHAR(100) NOT NULL,
      iso_year SMALLINT NOT NULL,
      iso_month TINYINT NOT NULL,
      puntos INT NOT NULL DEFAULT 0,
      created_by VARCHAR(190) NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY clase_mes (clase, iso_year, iso_month)
    ) $cc;";

    // Alumnos: entidad propia y reutilizable. Un alumno pertenece a una clase y
    // se referencia por id desde Deportes, Biblioteca y Partes, de modo que
    // renombrarlo no rompe su histórico (a diferencia de las clases, que se
    // vinculan por nombre). El índice único evita duplicados al darlos de alta
    // al vuelo desde los formularios: el prefijo nombre(100) mantiene la clave
    // por debajo del límite de bytes de InnoDB con utf8mb4.
    $sql7 = "CREATE TABLE {$p}lc_alumnos (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      nombre VARCHAR(190) NOT NULL,
      clase VARCHAR(50) NOT NULL,
      activo TINYINT(1) NOT NULL DEFAULT 1,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      created_by VARCHAR(190) NULL,
      PRIMARY KEY (id),
      UNIQUE KEY uniq_alumno (nombre(100), clase),
      KEY idx_clase (clase)
    ) $cc;";

    // Partes: una fila por parte, con el alumno al que pertenece. Sustituye a
    // lc_partes, que guardaba contadores agregados por clase y semana y no podía
    // convertirse con dbDelta (su clave única impedía varias filas por semana).
    // La tabla antigua se deja huérfana en vez de borrarla, por si hiciera falta.
    // alumno_id = 0 significa "sin alumno asignado": los puntos van igualmente a
    // la clase, lo que permite registrar un parte sin identificar al alumno.
    $sql8 = "CREATE TABLE {$p}lc_partes_alumnos (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      alumno_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
      clase VARCHAR(50) NOT NULL,
      iso_year SMALLINT NOT NULL,
      iso_week TINYINT NOT NULL,
      tipo VARCHAR(20) NOT NULL,
      notas TEXT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      created_by VARCHAR(190) NULL,
      PRIMARY KEY (id),
      KEY idx_semana (clase, iso_year, iso_week),
      KEY idx_alumno (alumno_id)
    ) $cc;";

    dbDelta($sql1); dbDelta($sql2); dbDelta($sql3); dbDelta($sql4); dbDelta($sql5); dbDelta($sql6);
    dbDelta($sql7); dbDelta($sql8);
  }

  // Migra datos del antiguo campo único "partes" (anterior a la versión 0.3.0) a
  // partes_positivos, asumiendo que todo lo registrado hasta ahora era positivo
  // (el formulario nunca permitió introducir negativos). Así el total no cambia
  // para las semanas ya guardadas.
  private static function migrate_partes_legacy($wpdb, $p){
    $table = "{$p}lc_partes";
    $has_legacy_column = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'partes'");
    if (!$has_legacy_column) return;

    $wpdb->query("UPDATE {$table} SET partes_positivos = partes WHERE partes_positivos = 0 AND partes_negativos = 0 AND partes <> 0");
  }

  // Migra el antiguo campo único "partes_negativos" (anterior a la separación
  // leve/grave de 2026-06-20) a "partes_leves", asumiendo la severidad menos
  // grave (el formulario nunca distinguió leve/grave antes de este cambio).
  // No se borra la columna vieja (dbDelta no borra columnas; queda huérfana e
  // inofensiva, igual que "partes" en migrate_partes_legacy()).
  private static function migrate_partes_severidad($wpdb, $p){
    $table = "{$p}lc_partes";
    $has_legacy_column = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'partes_negativos'");
    if (!$has_legacy_column) return;

    $wpdb->query("UPDATE {$table} SET partes_leves = partes_negativos WHERE partes_leves = 0 AND partes_graves = 0 AND partes_negativos <> 0");
  }
}
