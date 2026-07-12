# Instapaper-auth
Code-based auth for Instapaper's service, bridging OAuth2 to legacy devices

## Superseded
This project has been superseded by a more generic, multi-service approach. 

Please see: [https://github.com/webOSArchive/oauth-broker-for-webos](https://github.com/webOSArchive/oauth-broker-for-webos)

## Requirements
- A web server
- PHP
- A consumer key from Instapaper: https://www.instapaper.com/developers/v1/full-api 

## Install
- `git clone https://github.com/webOSArchive/instapaper-auth`
- `cd instapaper-auth`
- `composer require fivefilters/readability.php`
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
