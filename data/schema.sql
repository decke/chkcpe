--- CPE 2.3 DB
--- 
--- create the initial database:
---  sqlite3 cpe23.db
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
  cpeid INTEGER PRIMARY KEY NOT NULL,
  productid INTEGER NOT NULL,
  version VARCHAR(255) NOT NULL,
  cpefs VARCHAR(255) NOT NULL,
  FOREIGN KEY(productid) REFERENCES products(productid)
);

CREATE INDEX cpes_idx1 ON cpes (productid);

COMMIT;
