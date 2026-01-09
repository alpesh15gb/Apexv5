FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    supervisor \
    libzip-dev \
    gnupg2

# Add Microsoft Repo for SQL Server Drivers
RUN curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && curl https://packages.microsoft.com/config/debian/12/prod.list > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y msodbcsql18 mssql-tools18 unixodbc-dev

# Install SQLSRV Extensions
RUN pecl install sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv

# Allow Legacy SSL Protocols (TLS 1.0/1.1) for old MSSQL Servers
RUN sed -i 's/SECLEVEL=2/SECLEVEL=0/g' /etc/ssl/openssl.cnf




# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy Supervisor Configuration
COPY docker/supervisor/conf.d/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/supervisor/conf.d/worker.conf /etc/supervisor/conf.d/worker.conf


# Set working directory
WORKDIR /var/www/html

# Create system user to run Composer and Artisan Commands
# (Optional: Use root or a specific user. For simplicity, we use root or www-data)
# RUN useradd -G www-data,root -u 1000 -d /home/dev dev
# RUN mkdir -p /home/dev/.composer && \
#    chown -R dev:dev /home/dev

CMD ["php-fpm"]
