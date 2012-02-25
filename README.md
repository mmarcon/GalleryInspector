#Gallery Inspector

This simple PHP class was built to retrieve information about **Galleries, Photos and Thumbnails** from the DB created by [**Gallery 3**](http://gallery.menalto.com/gallery_3_begins).

This way one can use the Gallery UI to upload pictures on a website and this simple class to retrieve a JSON (or XML in the future) containing all the hierarchy of album and photos and render it however he likes.

Doing that with the default REST API provided with Gallery 3 was kind of a pain and it was very slow, so I thought I would write my own class that reads directly from the DB only the information that I actually need.