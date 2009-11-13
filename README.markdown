**Tag Sync** is an extension that works alongside the [Solspace Tag module](http://www.solspace.com/software/detail/tag/) to automatically keep a custom text field synchronized with each entry's tags, *making your tags searchable* via the Search module.

##Usage

After uploading and activating the extension, visit Tag Sync's extension settings screen. For each relevant weblog, choose the custom text field you'd like tags synced to. (Make sure this custom field is searchable, and I'd suggest you keep it hidden by default.) Once saved, the chosen field will be updated with current Tag data whenever an entry is published or updated.

You can also run a full synchronization of tags to your custom field by using the link provided on the extension settings screen for each weblog.  This is useful if you already have a bunch of tagged entries when you install the extension, and for periodic refreshes (i.e. when tags have been altered or merged using the Tag Manager, or if tags have been submitted via the public tag form, as these actions will not automatically update your custom fields).

Note that Tag Sync executes **one-way synchronization**. It will never interfere with the Tag module; it will only take data from the module and insert it into your custom field.

*Tag Sync has been tested with ExpressionEngine 1.6.8.*