# Instagram App

### 
Payload basic example

```json
{
   "module": "command",
   "method": "directMessage",
   "payload": {
     "threadId": "info",
     "message": "Winner, winner - chicken dinner"
   }
}
```
#### Get Instagram media

```json
{"method": "getMediaInfo", "payload": {"mediaId": "2040627589441440151_1441708245"}}
{"method": "getMediaInfo", "payload": {"mediaId": "2040627589441440151_1441708245"}} 
// [RTC] Item 28738729387766730515735365036277760 has been created in thread 340282366841710300949128253906845274935

```