# Fixing "Failed to fetch" / "could not read Username" on Install

FPP’s Plugin Manager gets the list of plugins from a central list ([fpp-pluginList](https://github.com/FalconChristmas/fpp-pluginList)). It then fetches each plugin’s `pluginInfo.json` from the URL in that list and uses the **srcURL** from that JSON to run `git clone`. If **FPP_Sbus_Plugin** is not in that list, or is listed with the wrong URL, FPP may try to clone from the wrong place and you’ll see:

- `Failed to fetch FPP_Sbus_Plugin using https://github.com/...`
- `fatal: could not read Username for 'https://github.com': No such device or address`

That’s a **plugin list / config** issue, not necessarily a network or system problem.

## Option 1: Add this plugin to the official FPP plugin list (recommended)

To make “Install” work from the Plugin Manager for everyone, this repo needs to be in the central list with the correct `pluginInfo.json` URL.

**Entry to add to** `pluginList.json` **in** [FalconChristmas/fpp-pluginList](https://github.com/FalconChristmas/fpp-pluginList):

```json
[ "FPP_Sbus_Plugin", "https://raw.githubusercontent.com/RandomActsofFrank/FPP_Sbus_Plugin/main/pluginInfo.json" ]
```

Add it inside the `"pluginList": [ ... ]` array (same format as the other lines). Then open a pull request. Once merged, FPP will fetch this plugin’s `pluginInfo.json` and use its `srcURL` (`https://github.com/RandomActsofFrank/FPP_Sbus_Plugin.git`) to clone. This repo uses the **main** branch.

## Option 2: Manual install (works without being in the list)

If you can’t add the plugin to the list yet, install manually:

1. Clone or download this repo on your computer.
2. Copy the whole `FPP_Sbus_Plugin` folder to the FPP plugins directory on the Pi (e.g. `/home/fpp/media/plugins/`).
3. On the Pi, run the plugin install script (e.g. `sudo /opt/fpp/scripts/install_plugin.sh FPP_Sbus_Plugin` or your FPP’s equivalent).
4. Restart FPP or reload the plugin list.

See the main [README.md](README.md) for full manual install steps.
