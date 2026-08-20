# Featuring posts on the home page

Six of the nine tiles are fed automatically: the newest News post fills the
large card, the next one the smaller card, and so on for YouTube and Project
work. You never choose what goes there — publishing decides it.

The **profile tile** and the **map tile** work differently. Each can rotate
through posts you pick, one at a time, with a row of dots underneath showing
where you are. Click a dot to jump, hover to pause. It is the pattern most news
and streaming sites use for their headline strip.

This is also the only way a **Misc** post reaches the home page, since Misc is
the one category without a tile of its own.

## Putting a post in a rotation

Two places, same switches:

- **In the post editor** — a "Home page" section with *Rotate on the profile
  tile* and *Rotate on the map tile*.
- **On the post list** (`/admin/content`) — a **Carousel** column with the same
  two checkboxes on every row. They save the moment you click; there is no Save
  button. This is the faster way to curate several posts at once.

The post list also has a **carousel filter** next to the category filter — *on
profile*, *on map*, *on either*, *on neither* — so you can see what is featured
without opening anything.

Any post can go in either rotation, or both, whatever its category. A post in a
rotation still appears everywhere else it normally would.

## What actually shows

Each tile shows **its own content first** — your profile on one, the map on the
other — then the posts you have flagged. With nothing flagged, both tiles look
exactly as they did before you started.

Three rules decide the rest:

- **Five posts per tile, maximum.** Past that the dot row stops being a useful
  position indicator, so only the newest five appear. The filter bar on the post
  list tells you when you are over — *"showing 5 of 7 — the 2 oldest never
  appear"* — so you are never guessing.
- **Newest first, by publish date.** Not by the order you ticked the boxes. If
  you flag a sixth post, the one that drops off is the one with the oldest
  publish date, which may not be the one you flagged first.
- **A post dated in the future still occupies a slot** and shows as *Scheduled*,
  the same as it would on any other tile. Move the date, or unflag it, if you
  want it held back.

A **suppressed** post disappears from its rotation along with everywhere else,
and reappears if you un-suppress it. You do not need to unflag it first.

## How a slide looks

The post's own image becomes a heavily muted backdrop for the tile, and on the
profile tile a small copy of it sits in the top corner. Your avatar stays where
it is on every slide.

The text fills the tile and fades out where it runs past the bottom, with
**More** underneath — the whole slide is a link to the full article. Nothing is
truncated to a fixed length; the tile shows as much as it has room for, which
is why the same post shows more text on a wide screen than a narrow one.

Visitors who have asked their system for reduced motion get no automatic
movement at all. The dots still work, so every slide is still reachable.

## Removing the samples

A fresh install ships with one sample post in each rotation, so the feature is
visible rather than hidden behind a checkbox. **Settings → Delete demo content**
removes them along with the rest of the sample content.

Anything *you* have touched is safe: flagging a post into a rotation counts as
editing it, so it stops being sample content from that moment and survives.
