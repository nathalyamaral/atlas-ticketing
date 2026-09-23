FROM php:8.4-cli

WORKDIR /app

COPY . .

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]