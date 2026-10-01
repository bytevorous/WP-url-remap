# Makefile for the URL Remap plugin.
#
# Produces dist/url-remap.zip whose single top-level entry is the
# folder "url-remap/" containing only the runtime files - the exact
# shape WordPress' "Add New -> Upload Plugin" expects.
#
# Usage:
#   make            # build dist/url-remap.zip
#   make clean      # remove the dist/ directory
#
# The build stages files into a temp dir first, so this Makefile, the
# dist zip, and any VCS metadata are never included in the plugin zip.

SLUG    := url-remap
DIST    := dist
STAGE   := $(DIST)/$(SLUG)
ZIP     := $(DIST)/$(SLUG).zip

# Files that belong inside the plugin. Add new runtime files here.
FILES   := url-remap.php index.php readme.txt

.PHONY: all build clean

all: build

build: clean
	@mkdir -p $(STAGE)
	@cp $(FILES) $(STAGE)/
	@cd $(DIST) && zip -rq $(SLUG).zip $(SLUG)
	@rm -rf $(STAGE)
	@echo "Built $(ZIP)"
	@echo "Upload via Plugins -> Add New -> Upload Plugin."

clean:
	@rm -rf $(DIST)
