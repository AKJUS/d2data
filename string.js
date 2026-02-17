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
    'tbl/bnet.json',
    'tbl/chinese-overlay.json',
    'tbl/commands.json',
    'tbl/item-gems.json',
    'tbl/item-modifiers.json',
    'tbl/item-nameaffixes.json',
    'tbl/item-names.json',
    'tbl/item-runes.json',
    'tbl/keybinds.json',
    'tbl/levels.json',
    'tbl/mercenaries.json',
    'tbl/monsters.json',
    'tbl/npcs.json',
    'tbl/objects.json',
    'tbl/presence-states.json',
    'tbl/quests.json',
    'tbl/shrines.json',
    'tbl/skills.json',
    'tbl/ui-controller.json',
    'tbl/ui.json',
    'tbl/vo.json',
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
