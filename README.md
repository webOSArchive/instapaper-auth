# pocket-auth
Code-based auth for Pocket's service, bridging OAuth2 to legacy devices

## Archival

In July 2025, Mozilla shut down Pocket. As a result, this service no longer works.

The archive remains to illustrate a pattern for supporting OAuth on legacy devices.

## Requirements
- A web server
- PHP
- A consumer key from Pocket: https://getpocket.com/developer/ 

## Install
- `git clone https://github.com/webOSArchive/pocket-auth`
- `cd pocket-auth`
- `composer require djchen/pocket-api-php`
- `cp config-example.php config.php`
- Modify config.php to include your consumer key, change any other global you want
- `mkdir cache/`
- Give the web service user ownership of the cache folder, eg: `chown www-data:www-data cache/`
- Protect the cache folder, nginx site config eg: 
```
location /cache/ {
		internal;
	}
```
