# Huhu - a javascript and php based Messenger

## What is Huhu?

### What it aims to be

Huhu is a messenger app, running on many mobile devices (via Phonegap) and in webbrowsers. It aims to be simple but also secure
and therefore uses a private-/public key mechanism for transfering messages between the server and the app.

### What it actually is

All the above but in a very early state, not very stable and proofed. A pre-alpha version with some bugs in it and still
missing a lot of features.


## What will I find in this repository?

In this repository you'll find the Huhu-**Backend**, which is written in PHP with Zend-Framework 1.10 and uses memcache as primary datastore and a 
mySQL-Database as a permanent backup-datastore.

**Please note that there currently exists no guides, except for the comments in the code. But I'm working on it, and you'll 
find <u>a well documented API and some guides how to set up</u> here soon.**

You'll find a guide, how to set up the backend on your dev-machine, here later. If you want to use the backend on my server 
(i.e. if you whish to develop the frontend), you can use it on http://we-hu.hu/api/.
You will also find a complete API-Documentation here later.

The **Frontend** is located in the "[Huhu-Frontend](https://github.com/JanST123/Huhu_Frontend)" repository here on github.


## The History (and why OpenSource)

I started working on the Huhu project in August of 2013. It was not planned as an open source project, but as an alternative to WhatsApp offering more security and possibilities, e.g. use it in a browser and therefore on a normal PC.
I wasn't in a hurry developing the app and i did it in my sparetime after i came home from my job.
When Facebook bought WhatsApp, alternative messengers started so spring up like mushrooms - some of them with big investors in the background. To the same effect the requirements to a messenger app started to grow, mainly concerning security things.

