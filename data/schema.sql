--- chkcpe DB
--- 
--- create the initial database:
---  sqlite3 chkcpe.db
---  > .read schema.sql
---  > .q

PRAGMA encoding = "UTF-8";

BEGIN TRANSACTION;

CREATE TABLE products (
  productid INTEGER PRIMARY KEY NOT NULL,
  vendor VARCHAR(50) NOT NULL,
  product VARCHAR(50) NOT NULL,
  deprecatedby VARCHAR(100) NOT NULL
);

CREATE INDEX products_idx1 ON products (vendor);
CREATE INDEX products_idx2 ON products (product);

CREATE TABLE cpes (
  productid INTEGER NOT NULL,
  cpefs VARCHAR(255) NOT NULL,
  FOREIGN KEY(productid) REFERENCES products(productid)
);

CREATE INDEX cpes_idx1 ON cpes (productid);

CREATE TABLE ports (
  origin VARCHAR(255) PRIMARY KEY NOT NULL,
  category VARCHAR(50) NOT NULL,
  portdir VARCHAR(50) NOT NULL,
  portname VARCHAR(255),
  version VARCHAR(50),
  maintainer VARCHAR(255),
  cpeuri VARCHAR(255),
  status VARCHAR(25)
);

CREATE INDEX ports_idx1 ON ports (status);

CREATE TABLE candidates (
  origin VARCHAR(255) NOT NULL,
  cpeuri VARCHAR(255)
);

CREATE INDEX candidates_idx1 ON candidates (origin);

COMMIT;
