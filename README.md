# Huhu - a javascript and php based Messenger

![image](https://raw.githubusercontent.com/JanST123/Huhu_Frontend/master/icons/icon.png)

## What is Huhu?

### What it aims to be

Huhu is a messenger app, running on many mobile devices (via Phonegap) and in HTML5 browsers. It aims to be simple but also secure
and therefore uses a private-/public key mechanism for transfering messages between the server and the app.

### What it actually is

All the above but in a very early state, not very stable and proofed. A pre-alpha version with some bugs in it and still missing a lot of features. But it is useable.


## What will I find in this repository?

In this repository you'll find the Huhu-**Backend**, which is written in PHP5.4 with Zend-Framework 1.10 and uses memcache as primary datastore and a 
mySQL-Database as a permanent "2nd level"-datastore.

**Please have a look to our [Wiki](https://github.com/JanST123/Huhu/wiki) where you will find some documentation and a tutorial how to setup the backend**

If you want to use the backend on my server (i.e. if you whish to develop the frontend), you can use it on http://we-hu.hu/api/.

You can also use the [PHP-Doc](https://we-hu.hu/api/docs/index.html) as a temporary API-Documentaion, but you will also find a complete **API-Documentation** here later.

The **Frontend** is located in the "[Huhu-Frontend](https://github.com/JanST123/Huhu_Frontend)" repository here on github.


## The History (and why OpenSource)

I started working on the Huhu project in August 2013. It was not planned as an open source project, but as an alternative to WhatsApp and GoogleTalk, combining the best of both and offering more security and possibilities, e.g. to use it in a browser and therefore on a normal PC.

I wasn't in a hurry developing the app and i did it in my sparetime after i came home from my job.
When Facebook bought WhatsApp, alternative messengers started so spring up like mushrooms - some of them with big investors in the background. To the same effect the requirements to a messenger app started to grow, mainly concerning security things.
This means a lot of more work to me, and more difficulties to handle. In addition to that I will not have much time and passion for sparetime-programming in the future, as I become a father in a few months :-)

This is why I decided to publish my code and ideas I have until now, to prevent my work from getting moldy on my computer, and so I made this an open source project. And even if there are **a lot of messenger apps now, there is currently no good one which is open source and therefore reviewed by many people which are aware of security things**.


Please feel free, to use and test the app, make any changes you may think were good and contribute to the project.

And as this is my first open source project, please also feel free, to give me hints if I did anything wrong or forgotten something.

