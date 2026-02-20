const fs = require('fs');

function sortObjectKeys(obj) {
    return Object.fromEntries(Object.entries(obj).sort((a, b) => a[0].localeCompare(b[0])));
}

function discardGarbage(lines) {
    while (lines.length && ![
        'A4Q2ExpansionSuccessTyrael',
        'Cutthroat1',
        'WarrivAct1IntroGossip1',
    ].includes(lines[0])) {
        lines.shift();
    }

    return lines;
}

function processLanguage(lang) {
    let strings = {};

    [
      'string.tbl',
      'expansionstring.tbl',
      'patchstring.tbl',
    ].forEach(name => {
      console.log('Processing: ', lang, name);

      let lines = discardGarbage(fs.readFileSync('tbl/' + lang + '/' + name).toString().split('\0'));

      while (lines.length) {
            let key = lines.shift(), str = lines.shift();
    
            if (key.trim().length) {
                strings[key.trim()] = str;
            }    
        }
    });
    
    fs.writeFileSync('./json/localestrings-' + lang + '.json', JSON.stringify(sortObjectKeys(strings), null, ' '));

    return strings;
}

[
    'chi',
    'deu',
    'esp',
    'fra',
    'ita',
    'kor',
    'pol',
].forEach(processLanguage);

let allStrings = processLanguage('eng');

[
    'tbl/strings/bnet.json',
    'tbl/strings/chinese-overlay.json',
    'tbl/strings/commands.json',
    'tbl/strings/item-gems.json',
    'tbl/strings/item-modifiers.json',
    'tbl/strings/item-nameaffixes.json',
    'tbl/strings/item-names.json',
    'tbl/strings/item-runes.json',
    'tbl/strings/keybinds.json',
    'tbl/strings/levels.json',
    'tbl/strings/mercenaries.json',
    'tbl/strings/monsters.json',
    'tbl/strings/npcs.json',
    'tbl/strings/objects.json',
    'tbl/strings/presence-states.json',
    'tbl/strings/quests.json',
    'tbl/strings/shrines.json',
    'tbl/strings/skills.json',
    'tbl/strings/ui-controller.json',
    'tbl/strings/ui.json',
    'tbl/strings/vo.json',
].forEach(filename => {
    console.log('Reading: ', filename);
    let data = JSON.parse(fs.readFileSync(filename).toString().trim());

    data.forEach(entry => {
        if (entry.Key && entry.enUS) {
            if (entry.Key in allStrings) {
                console.warn('Duplicate key: ', entry.Key);
            }
            allStrings[entry.Key] = entry.enUS;
        }
    });
});

fs.writeFileSync('./json/allstrings-eng.json', JSON.stringify(sortObjectKeys(allStrings), null, ' '));
