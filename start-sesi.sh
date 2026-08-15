#!/bin/bash
# Script mulai sesi GUDEG
# Jalankan: bash start-sesi.sh

REPO="https://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@github.com/aprianto199304012025041002-wq/gudeg.git"
DIR="/home/claude/gudeg"

echo "🔄 Clone repo GUDEG..."
git clone "$REPO" "$DIR" 2>&1

echo ""
echo "📋 BACKLOG.md:"
echo "=============="
cat "$DIR/BACKLOG.md"
