#!/bin/bash
# Fix Deployment Script
# This script removes the corrupt .env file and replaces it with a clean one.

echo "Removing corrupt .env file..."
rm -f .env

echo "Creating fresh .env from example..."
cp .env.example .env

echo "Done! .env has been reset."
echo "IMPORTANT: Please edit .env now to set your DB_PASSWORD and SYNC_API_TOKEN."
