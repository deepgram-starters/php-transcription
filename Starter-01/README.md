# Deepgram PHP Starter

This sample demonstrates interacting with the Deepgram API from a PHP server. It uses the Deepgram API to handle API calls, and has a React companion application to interact with the PHP integration.

## Sign-up to Deepgram

Before you start, it's essential to generate a Deepgram API key to use in this project. [Sign-up now for Deepgram](https://console.deepgram.com/signup).

## Quickstart

### Manual

Follow these steps to get started with this starter application.

#### Clone the repository

Go to GitHub and [clone the repository](https://github.com/deepgram-starters/deepgram-python-starters).

#### Install php

If you haven't already, you need to install PHP on your system. You can download and install it by following the instructions on the PHP website: https://www.php.net/manual/en/install.php


#### Install composer

If you haven't already, you need to install Composer on your system. You can download and install it by following the instructions on the Composer website: https://getcomposer.org/download/


#### Install dependencies

Use composer to install the project dependencies in the `Starter 01` directory.

```bash
cd ./Starter-01
composer install
```

#### Edit the config file

Copy the text from `.env-sample` and create a new file called `.env`. Paste in the code and enter your API key you generated in the [Deepgram console](https://console.deepgram.com/).

```bash
port=8080
deepgram_api_key=api_key
```

#### Run the application

Once running, you can [access the application in your browser](http://localhost:8080/).

```bash
php -S localhost:8080 -d post_max_size=200M -d upload_max_filesize=200M
```
