CREATE DATABASE IF NOT EXISTS rutkacms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rutkacms;

CREATE TABLE IF NOT EXISTS pages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pa_pid INT UNSIGNED DEFAULT NULL,
  pa_title VARCHAR(255) NOT NULL,
  pa_weight INT NOT NULL DEFAULT 50,
  pa_uri VARCHAR(255) NOT NULL,
  pa_description VARCHAR(255) DEFAULT NULL,
  pa_keywords VARCHAR(255) DEFAULT NULL,
  pa_content MEDIUMTEXT DEFAULT NULL,
  pa_title_en VARCHAR(255) DEFAULT NULL,
  pa_uri_en VARCHAR(255) DEFAULT NULL,
  pa_description_en VARCHAR(255) DEFAULT NULL,
  pa_keywords_en VARCHAR(255) DEFAULT NULL,
  pa_content_en MEDIUMTEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pages_uri (pa_uri)
);

CREATE TABLE IF NOT EXISTS news (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ne_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ne_title VARCHAR(255) NOT NULL,
  ne_content MEDIUMTEXT DEFAULT NULL,
  ne_title_en VARCHAR(255) DEFAULT NULL,
  ne_content_en MEDIUMTEXT DEFAULT NULL,
  ne_image1 VARCHAR(255) DEFAULT NULL,
  ne_image2 VARCHAR(255) DEFAULT NULL,
  ne_image3 VARCHAR(255) DEFAULT NULL,
  ne_pdf1 VARCHAR(255) DEFAULT NULL,
  ne_pdf2 VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS publicationtype (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  py_name VARCHAR(120) NOT NULL,
  py_name_en VARCHAR(120) DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS publications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pu_title VARCHAR(255) NOT NULL,
  pu_authors VARCHAR(255) DEFAULT NULL,
  pu_year VARCHAR(4) DEFAULT NULL,
  pu_journal VARCHAR(255) DEFAULT NULL,
  pu_volume VARCHAR(100) DEFAULT NULL,
  pu_book VARCHAR(255) DEFAULT NULL,
  pu_publisher VARCHAR(255) DEFAULT NULL,
  pu_pages VARCHAR(64) DEFAULT NULL,
  pu_typeid INT UNSIGNED DEFAULT NULL,
  pu_pdf VARCHAR(255) DEFAULT NULL,
  pu_doi VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_publications_type (pu_typeid),
  CONSTRAINT fk_publications_type FOREIGN KEY (pu_typeid) REFERENCES publicationtype(id)
);

INSERT INTO pages (pa_pid, pa_title, pa_weight, pa_uri, pa_description, pa_keywords, pa_content, pa_title_en, pa_uri_en)
VALUES
  (NULL, 'Domov', 10, 'domov', 'Zacetna stran', 'rutka,cms', '<p>Pozdravljeni v RutkaCMS.</p>', 'Home', 'home'),
  (NULL, 'Novice', 20, 'novice', 'Aktualne novice', 'novice,aktualno', '<p>Seznam novic.</p>', 'News', 'news')
ON DUPLICATE KEY UPDATE pa_title = VALUES(pa_title);

INSERT INTO news (ne_date, ne_title, ne_content, ne_title_en, ne_content_en)
VALUES
  (NOW(), 'Zagon sistema', 'Nova Docker razvojna postavitev deluje.', 'System start', 'New Docker development stack is working.');

INSERT INTO publicationtype (py_name, py_name_en)
VALUES
  ('Clanek', 'Article'),
  ('Konferenca', 'Conference')
ON DUPLICATE KEY UPDATE py_name_en = VALUES(py_name_en);

INSERT INTO publications (pu_title, pu_authors, pu_year, pu_typeid, pu_doi)
VALUES
  ('Primer publikacije', 'K. Kenda', '2026', 1, '10.0000/example-doi');
