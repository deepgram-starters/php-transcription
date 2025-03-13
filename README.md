# PHP Transcription Starter

[![Discord](https://dcbadge.vercel.app/api/server/xWRaCDBtW4?style=flat)](https://discord.gg/xWRaCDBtW4)

This sample demonstrates interacting with the Deepgram API from a PHP server. It uses the Deepgram API to handle API calls, and has a javascript client built from web components.

## What is Deepgram?

[Deepgram’s](https://deepgram.com/) voice AI platform provides APIs for speech-to-text, text-to-speech, and full speech-to-speech voice agents. Over 200,000+ developers use Deepgram to build voice AI products and features.

## Sign-up to Deepgram

Before you start, it's essential to generate a Deepgram API key to use in this project. [Sign-up now for Deepgram and create an API key](https://console.deepgram.com/signup?jump=keys).

## Quickstart

### Manual

Follow these steps to get started with this starter application.

#### Clone the repository

Go to GitHub and [clone the repository](https://github.com/deepgram-starters/deepgram-python-starters).

#### Install PHP

If you haven't already, you need to install PHP on your system. You can download and install it by following the instructions on the PHP website: https://www.php.net/manual/en/install.php

#### Install composer

If you haven't already, you need to install Composer on your system. You can download and install it by following the instructions on the Composer website: https://getcomposer.org/download/

If you use homebrew, you can install composer with the following command:

```bash
brew install composer
```

#### Install dependencies

Use composer to install the project dependencies in the root directory.

```bash
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

## Issue Reporting

If you have found a bug or if you have a feature request, please report them at this repository issues section. Please do not report security vulnerabilities on the public GitHub issue tracker. The [Security Policy](./SECURITY.md) details the procedure for contacting Deepgram.

## Getting Help

We love to hear from you so if you have questions, comments or find a bug in the project, let us know! You can either:

- [Open an issue in this repository](https://github.com/deepgram-starters/php-transcription/issues/new)
- [Join the Deepgram Github Discussions Community](https://github.com/orgs/deepgram/discussions)
- [Join the Deepgram Discord Community](https://discord.gg/xWRaCDBtW4)

## Author

[Deepgram](https://deepgram.com)

## License

This project is licensed under the MIT license. See the [LICENSE](./LICENSE) file for more info.
