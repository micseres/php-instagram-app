# Instagram App

## How to run in ERP

Setup 'chat_instagram_reciever' supervisor command and run

## How to run service

Setup https://github.com/micseres/php-instagram-app

Check .env

Setup 'instagram_app_server' supervisor command

## Command examples 

Payload basic example

```json
{

   "processor": "direct",
   "method": "directMessage",
   "payload": {
     "threadId": "info",
     "message": "Winner, winner - chicken dinner"
   }
}
```
Get Instagram media example

```json
{
    "processor": "command",
    "method": "getMediaInfo", 
    "payload": {
        "mediaId": "2040627589441440151_1441708245"
        }
}
```

## Processors

'direct' - for send direct messages with ack async response
'command' - instagram API calls
