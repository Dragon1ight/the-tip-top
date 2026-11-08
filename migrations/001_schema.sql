CREATE TABLE IF NOT EXISTS boutique (
  id INT AUTO_INCREMENT PRIMARY KEY, nom VARCHAR(120) NOT NULL, adresse VARCHAR(190) NOT NULL,
  ville VARCHAR(80) NOT NULL, code_postal VARCHAR(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lot (
  id INT PRIMARY KEY, libelle VARCHAR(120) NOT NULL, categorie VARCHAR(30) NOT NULL,
  valeur_euros DECIMAL(8,2) NOT NULL, pourcentage_cible DECIMAL(5,2) NOT NULL, quantite_prevue INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS participant (
  id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(190) NOT NULL UNIQUE, mot_de_passe_hash VARCHAR(255) NULL,
  nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, date_naissance DATE NULL, civilite VARCHAR(12) NULL,
  code_postal VARCHAR(10) NULL, sso_fournisseur VARCHAR(20) NULL, sso_identifiant VARCHAR(190) NULL,
  marketing_optin TINYINT(1) NOT NULL DEFAULT 0, email_valide_le DATETIME NULL, suspendu TINYINT(1) NOT NULL DEFAULT 0,
  cree_le DATETIME NOT NULL, supprime_le DATETIME NULL, UNIQUE KEY uq_sso (sso_fournisseur, sso_identifiant)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS consentement (
  id INT AUTO_INCREMENT PRIMARY KEY, participant_id INT NOT NULL, type VARCHAR(20) NOT NULL, accorde TINYINT(1) NOT NULL,
  horodatage DATETIME NOT NULL, preuve VARCHAR(255) NOT NULL, KEY idx_part (participant_id),
  CONSTRAINT fk_cons_part FOREIGN KEY (participant_id) REFERENCES participant(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS code_ticket (
  valeur CHAR(10) PRIMARY KEY, lot_id INT NOT NULL, participant_id INT NULL, boutique_emission_id INT NULL,
  genere_le DATETIME NOT NULL, distribue_le DATETIME NULL, utilise_le DATETIME NULL, KEY idx_lot (lot_id), KEY idx_part (participant_id),
  CONSTRAINT fk_code_lot FOREIGN KEY (lot_id) REFERENCES lot(id),
  CONSTRAINT fk_code_part FOREIGN KEY (participant_id) REFERENCES participant(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS employe (
  id INT AUTO_INCREMENT PRIMARY KEY, boutique_id INT NULL, nom VARCHAR(120) NOT NULL, identifiant VARCHAR(190) NOT NULL UNIQUE,
  mot_de_passe_hash VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL DEFAULT 'employe', actif TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_emp_bout FOREIGN KEY (boutique_id) REFERENCES boutique(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gain (
  id INT AUTO_INCREMENT PRIMARY KEY, participant_id INT NOT NULL, code_valeur CHAR(10) NOT NULL UNIQUE, lot_id INT NOT NULL,
  statut VARCHAR(12) NOT NULL DEFAULT 'a_retirer', mode_remise VARCHAR(12) NULL, adresse_expedition VARCHAR(255) NULL,
  boutique_id INT NULL, employe_id INT NULL, attribue_le DATETIME NOT NULL, remis_le DATETIME NULL, date_limite DATETIME NOT NULL,
  KEY idx_part (participant_id), KEY idx_statut (statut),
  CONSTRAINT fk_gain_part FOREIGN KEY (participant_id) REFERENCES participant(id),
  CONSTRAINT fk_gain_code FOREIGN KEY (code_valeur) REFERENCES code_ticket(valeur),
  CONSTRAINT fk_gain_lot FOREIGN KEY (lot_id) REFERENCES lot(id),
  CONSTRAINT fk_gain_bout FOREIGN KEY (boutique_id) REFERENCES boutique(id),
  CONSTRAINT fk_gain_emp FOREIGN KEY (employe_id) REFERENCES employe(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tirage (
  id INT PRIMARY KEY, execute_le DATETIME NULL, graine CHAR(64) NULL, empreinte_liste CHAR(64) NULL,
  effectif_participants INT NULL, gagnant_participant_id INT NULL, verrouille TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_tir_part FOREIGN KEY (gagnant_participant_id) REFERENCES participant(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inscription_tirage (
  id INT AUTO_INCREMENT PRIMARY KEY, tirage_id INT NOT NULL, participant_id INT NOT NULL, inscrit_le DATETIME NOT NULL,
  UNIQUE KEY uq_tirage (tirage_id, participant_id),
  CONSTRAINT fk_ins_tir FOREIGN KEY (tirage_id) REFERENCES tirage(id),
  CONSTRAINT fk_ins_part FOREIGN KEY (participant_id) REFERENCES participant(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS journal_remise (
  id INT AUTO_INCREMENT PRIMARY KEY, gain_id INT NOT NULL, employe_id INT NULL, boutique_id INT NULL, action VARCHAR(30) NOT NULL,
  motif_substitution VARCHAR(255) NULL, horodatage DATETIME NOT NULL, KEY idx_gain (gain_id),
  CONSTRAINT fk_jr_gain FOREIGN KEY (gain_id) REFERENCES gain(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS journal_admin (
  id INT AUTO_INCREMENT PRIMARY KEY, auteur VARCHAR(190) NOT NULL, action VARCHAR(60) NOT NULL, cible VARCHAR(190) NULL, horodatage DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jeton (
  hash CHAR(64) PRIMARY KEY, type VARCHAR(20) NOT NULL, participant_id INT NOT NULL, expire_le DATETIME NOT NULL,
  CONSTRAINT fk_jet_part FOREIGN KEY (participant_id) REFERENCES participant(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS api_nonce (nonce VARCHAR(64) PRIMARY KEY, expire_le DATETIME NOT NULL) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quota (cle VARCHAR(190) PRIMARY KEY, compteur INT NOT NULL, fin_fenetre DATETIME NOT NULL) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS newsletter (
  id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(190) NOT NULL UNIQUE, horodatage DATETIME NOT NULL, preuve VARCHAR(255) NOT NULL, desinscrit_le DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sessions (
  sess_id VARBINARY(128) NOT NULL PRIMARY KEY, sess_data BLOB NOT NULL, sess_lifetime INT UNSIGNED NOT NULL, sess_time INT UNSIGNED NOT NULL, KEY idx_life (sess_lifetime)
) ENGINE=InnoDB;

INSERT IGNORE INTO lot VALUES
 (1,'Un infuseur à thé','infuseur',12.00,60.00,300000),
 (2,'Une boîte de 100 g de thé détox ou d''infusion','detox',15.00,20.00,100000),
 (3,'Une boîte de 100 g de thé signature','signature',22.00,10.00,50000),
 (4,'Un coffret découverte d''une valeur de 39 €','coffret39',39.00,6.00,30000),
 (5,'Un coffret découverte d''une valeur de 69 €','coffret69',69.00,4.00,20000),
 (6,'Un an de thé, d''une valeur de 360 €','gros_lot',360.00,0.00,1);

INSERT IGNORE INTO boutique (id,nom,adresse,ville,code_postal) VALUES
 (1,'Thé Tip Top Bastille','18 rue Léon Frot','Paris','75011'),(2,'Thé Tip Top Marais','Rue des Archives','Paris','75004'),
 (3,'Thé Tip Top Lyon','Rue de la République','Lyon','69002'),(4,'Thé Tip Top Bordeaux','Rue Sainte-Catherine','Bordeaux','33000'),
 (5,'Thé Tip Top Lille','Rue de Béthune','Lille','59000'),(6,'Thé Tip Top Nantes','Rue Crébillon','Nantes','44000'),
 (7,'Thé Tip Top Toulouse','Rue Saint-Rome','Toulouse','31000'),(8,'Thé Tip Top Strasbourg','Rue des Hallebardes','Strasbourg','67000'),
 (9,'Thé Tip Top Marseille','Rue Paradis','Marseille','13001'),(10,'Thé Tip Top Nice','12 rue Masséna','Nice','06000');

INSERT IGNORE INTO tirage (id) VALUES (1);
