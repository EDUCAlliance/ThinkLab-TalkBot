# ThinkLab-TalkBot
This is the code for our chatbot, which will then be used as a bot for Nextcloud Talk on the EDUC Virtual Campus.
This is a project within the EDUC ThinkLab

## what does this script do?
So far, the code is not doing much. As soon as the bot is activated in a chat, a webhook is sent after every message sent in this chat, to a URL that is defined in the bot creation process. This PHP script is there to receive this webhook, extract the message sent and then only send back a “Hello world”.

### install guide
First of all, you have to install a bot. The term “register” would almost be more appropriate here, as you simply tell Nextcloud to send a webhook to a specific URL after each message (as soon as this bot is activated in the chat)
```
cd /path/to/nextcloud-occ-file && sudo -u www-data php occ talk:bot:install -f webhook,response "Name of the Bot" "Token" "https://Domain-of-Nextcloud.de/script-to-handle-webhook.php"
```
If you want to check, if the "installation" of the bot is correct, you can see an list of all bots with this command (you should now also be able to activate the bot in the Conversation settings of an Nextcloud Talk Chat)
```
sudo  -u www-data php occ talk:bot:list
```
