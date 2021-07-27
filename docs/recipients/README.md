# Recipients

You can specify the **recipients** when configuring a [message](/messages/). This determines who the message will be sent to whenever it is triggered.

:::tip Unique Messages
Each recipient will receive their own unique copy of the message. The [automatic variables](/messages/variables/) will be re-parsed for each individual recipient.
:::

A collection of recipients can be generated in many ways...

<img class="dropshadow" :src="$withBase('/images/recipients/select-type.png')" alt="" style="max-width:400px; margin-top:10px">

## All users

The message will be sent to **all Users in the system**. 

## All admins

The message will be sent to all **Admins** of the system.

## All users in selected group(s)

Select which user group(s) should receive the message.

[SCREENSHOT]

## Specific users

Select individual Users to receive the message.

[SCREENSHOT]

## Specific email addresses

Enter specific email addresses to receive the message.

[SCREENSHOT]

## Custom selection

Using Twig, generate a custom collection of Users or email addresses.

[EXPAND DOCS, INCLUDE SCREENSHOT]

 - [Get a collection of Users](/recipients/custom-users/)
 - [Get a collection of email addresses](/recipients/custom-emails/)