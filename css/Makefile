all: chkcpe.min.css

chkcpe.min.css:
	sassc -t compressed chkcpe.sass $@

clean:
	rm -f chkcpe.min.css

.PHONY: all clean
