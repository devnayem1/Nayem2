-- LuckBox schema. Import into an empty MySQL database (utf8mb4).
CREATE TABLE admins(id INT AUTO_INCREMENT PRIMARY KEY,username VARCHAR(50) NOT NULL UNIQUE,pass_hash VARCHAR(255) NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;
CREATE TABLE prizes(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(80) NOT NULL,description VARCHAR(255) NOT NULL DEFAULT '',`value` VARCHAR(30) NOT NULL DEFAULT '',image VARCHAR(120) NOT NULL DEFAULT '',odds INT NOT NULL DEFAULT 0 COMMENT 'basis points: 10000 = 100%',status TINYINT NOT NULL DEFAULT 1,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(status)) ENGINE=InnoDB;
CREATE TABLE game_sessions(game_id CHAR(24) PRIMARY KEY,seed CHAR(64) NOT NULL,commit_hash CHAR(64) NOT NULL,boxes TINYINT NOT NULL,roll SMALLINT NOT NULL,prize_id INT NULL,win_box TINYINT NULL,status ENUM('open','done') NOT NULL DEFAULT 'open',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(created_at)) ENGINE=InnoDB;
CREATE TABLE game_results(id INT AUTO_INCREMENT PRIMARY KEY,game_id CHAR(24) NOT NULL UNIQUE,selected_box TINYINT NOT NULL,result ENUM('win','lose') NOT NULL,prize_id INT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(created_at),INDEX(prize_id),INDEX(result)) ENGINE=InnoDB;
CREATE TABLE settings(k VARCHAR(40) PRIMARY KEY,v VARCHAR(255) NOT NULL) ENGINE=InnoDB;
INSERT INTO settings VALUES('boxes','3');
-- DEMO prizes (edit or delete in admin). Odds 5/10/15/20 % => 50 % "no prize".
INSERT INTO prizes(name,description,`value`,odds) VALUES('Gold Voucher (demo)','Demo prize','$100',500),('Wireless Earbuds (demo)','Demo prize','$60',1000),('Gift Card (demo)','Demo prize','$25',1500),('Bonus Pack (demo)','Demo prize','$10',2000);
-- No admin is created here: open admin.php once and create your own (password is hashed with password_hash).
