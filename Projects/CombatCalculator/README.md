# Hero vs. Death

**Name:** Blake Tapie

**Email:** Batapie@uno.edu

**ID:** 2621988

---

## Bells and Whistles / Enhancements
- Added **custom hero name** input.
- Implemented **hero stats allocation** system (Strength, Health, Magic).
- Magic can either **heal** or **boost attack temporarily**, chosen randomly.
- Combat includes log updates for each attack.
- End conditions include:
  - Victory if Death is defeated.
  - Game Over if the hero’s HP reaches 0.
  - Special edge-case handling when both the hero and Death die at the same time (player loses).

---

## Artwork Credits
- **Death enemy images:** [PNGEgg Grim Reaper](https://www.pngegg.com/en/search?q=grim+reaper)
- **Magic icon:** [Kindled Spirits Blog](https://kindledspirits.blog/2020/11/29/the-eternal-appeal-of-books-about-magic/)
- **Sword icon:** [Craiyon AI-generated image](https://www.craiyon.com/en/image/DIum-UZvSViJqonDSeFfPA)

---

## Known Bugs / Issues
- Rare edge cases required extra condition checks (e.g., when both player and enemy HP reach 0 at the same time). Fixed with ordered condition logic, but worth keeping in mind.
- Previously, “Victory” and “Game Over” text could **stack together** in certain conditions. This has been resolved by reordering conditions.
- Images initially displayed as white boxes due to asset path mismatches. This was corrected by ensuring proper placement in the `assets/` folder.

---

## Notes for Playing
- Allocate all points before beginning the fight.
- Use magic strategically: it might heal or give a one-turn attack boost.
- Escape is only possible after defeating Death.
- If you lose, you’ll return to the main menu to try again.
