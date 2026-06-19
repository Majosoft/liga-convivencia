<?php
if (!defined('ABSPATH')) exit;

class LC_Activator {
  public static function activate(){
    global $wpdb; require_once ABSPATH.'wp-admin/includes/upgrade.php';
    $cc = $wpdb->get_charset_collate(); $p = $wpdb->prefix;

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
      participantes TEXT NULL,
      notas TEXT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      created_by VARCHAR(190) NULL,
      PRIMARY KEY (id),
      UNIQUE KEY uniq_match (deporte, iso_year, iso_week, equipo_a, equipo_b)
    ) $cc;";

    $sql3 = "CREATE TABLE {$p}lc_partes (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      clase VARCHAR(50) NOT NULL,
      iso_year SMALLINT NOT NULL,
      iso_week TINYINT NOT NULL,
      partes INT DEFAULT 0,
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
      ganador VARCHAR(20) NULL,
      notas TEXT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      created_by VARCHAR(190) NULL,
      PRIMARY KEY (id),
      KEY idx_actividad_week (actividad, iso_year, iso_week)
    ) $cc;";

    dbDelta($sql1); dbDelta($sql2); dbDelta($sql3); dbDelta($sql4);
  }
}
