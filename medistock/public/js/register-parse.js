// MediStock registration - reads the OCR text of an ID and finds the fields
// (simple rules + regular expressions; OCR is never 100% so the user reviews the result)

var ID_TYPES = {
    philsys: { name: "Philippine National ID (PhilSys)", pattern: /\b(\d{4})[\s-]?(\d{4})[\s-]?(\d{4})[\s-]?(\d{4})\b/, joiner: "-" },
    license: { name: "Driver's License (LTO)", pattern: /\b([A-Z]\d{2})[\s-]?(\d{2})[\s-]?(\d{6})\b/, joiner: "-" },
    passport: { name: "Philippine Passport", pattern: /\b([A-Z]{1,2}\d{7}[A-Z]?)\b/, joiner: "" },
    umid: { name: "UMID", pattern: /\b(\d{4})[\s-]?(\d{7})[\s-]?(\d)\b/, joiner: "-" },
    test: { name: "Sample / test ID (demo only)", pattern: /\b([A-Z0-9][A-Z0-9-]{5,19})\b/, joiner: "" }
};

var MONTHS = { JAN: 1, FEB: 2, MAR: 3, APR: 4, MAY: 5, JUN: 6, JUL: 7, AUG: 8, SEP: 9, OCT: 10, NOV: 11, DEC: 12 };

function pad2(n) { return (n < 10 ? "0" : "") + n; }

function makeDate(y, m, d) {
    y = parseInt(y, 10); m = parseInt(m, 10); d = parseInt(d, 10);
    if (y < 1900 || y > 2100 || m < 1 || m > 12 || d < 1 || d > 31) { return ""; }
    var test = new Date(y, m - 1, d);
    if (test.getFullYear() != y || test.getMonth() != m - 1 || test.getDate() != d) { return ""; }
    return y + "-" + pad2(m) + "-" + pad2(d);
}

// find every date in a piece of text, returns ["YYYY-MM-DD", ...]
function findDates(text) {
    var found = [];
    var up = text.toUpperCase();
    var m;

    var r1 = /\b(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})\b/g;      // 2000/01/15
    while ((m = r1.exec(up)) !== null) { var a = makeDate(m[1], m[2], m[3]); if (a) { found.push(a); } }

    var r2 = /\b(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})\b/g;      // 01/15/2000 (month first)
    while ((m = r2.exec(up)) !== null) {
        var b = makeDate(m[3], m[1], m[2]);
        if (!b) { b = makeDate(m[3], m[2], m[1]); }                // maybe day first
        if (b) { found.push(b); }
    }

    var r3 = /\b([A-Z]{3})[A-Z]*\.?\s+(\d{1,2}),?\s+(\d{4})\b/g;   // JANUARY 15, 2000
    while ((m = r3.exec(up)) !== null) { if (MONTHS[m[1]]) { var c = makeDate(m[3], MONTHS[m[1]], m[2]); if (c) { found.push(c); } } }

    var r4 = /\b(\d{1,2})\s+([A-Z]{3})[A-Z]*\.?,?\s+(\d{4})\b/g;   // 15 JAN 2000
    while ((m = r4.exec(up)) !== null) { if (MONTHS[m[2]]) { var d = makeDate(m[3], MONTHS[m[2]], m[1]); if (d) { found.push(d); } } }

    return found;
}

function cleanLine(s) { return s.replace(/[|_~=*]+/g, " ").replace(/\s+/g, " ").trim(); }

// text after a label (same line, or the next line when the label stands alone)
function valueAfter(lines, labelRegex, stopRegex) {
    for (var i = 0; i < lines.length; i++) {
        var m = lines[i].match(labelRegex);
        if (m) {
            var rest = cleanLine(lines[i].substring(m.index + m[0].length).replace(/^[\s:\/.-]+/, ""));
            if (rest.length >= 2) { return rest; }
            if (i + 1 < lines.length) {
                var next = cleanLine(lines[i + 1]);
                if (next.length >= 2 && !(stopRegex && stopRegex.test(next))) { return next; }
            }
        }
    }
    return "";
}

function onlyLetters(s) { return cleanLine(s.replace(/[^A-Za-z\u00D1\u00F1.\s'-]/g, " ")).toUpperCase(); }

// a real name has words of 3 or more letters (OCR garbage like "L LS IT BI" does not)
function nameQuality(name) {
    var words = name.split(" ").filter(function (w) { return w.length > 0; });
    if (name.length < 5 || words.length < 2) { return false; }
    var short = words.filter(function (w) { return w.replace(/[^A-Z\u00D1]/g, "").length < 3; }).length;
    return short <= Math.floor(words.length / 4);
}

// PhilSys / similar cards: the names are printed under the small labels (last, given, middle)
function findNamePositional(lines) {
    var labelWords = /\b(APELYIDO|LAST|NAME|NAMES|MGA|PANGALAN|GIVEN|GITNANG|MIDDLE|PETSA|NG|KAPANGANAKAN|BIRTH|DATE|OF|TIRAHAN|ADDRESS|SURNAME)\b/gi;
    var start = -1;
    for (var i = 0; i < lines.length; i++) {
        if (/(APELYIDO|LAST\s*NAME)/i.test(lines[i])) { start = i; break; }
    }
    if (start < 0) { return ""; }
    var end = Math.min(lines.length, start + 9);
    for (var j = start + 1; j < end; j++) {
        if (/(BIRTH|KAPANGANAKAN|PETSA)/i.test(lines[j])) { end = j; break; }
    }
    var values = [];
    for (var k = start; k < end; k++) {
        var t = lines[k].replace(/[^A-Za-z\u00D1\u00F1.\s'-]/g, " ").replace(labelWords, " ");
        var words = t.split(/\s+/).filter(function (w) { return w.replace(/[^A-Za-z\u00D1\u00F1]/g, "").length >= 3; });
        if (words.length > 0) { values.push(words.join(" ").toUpperCase()); }
    }
    if (values.length >= 3) { return values[1] + " " + values[2] + " " + values[0]; }   // given middle last
    if (values.length == 2) { return values[1] + " " + values[0]; }
    if (values.length == 1) { return values[0]; }
    return "";
}

function cleanAddress(a) {
    a = a.replace(/[{}\[\]|<>~^`]/g, " ").replace(/\s+/g, " ").trim();
    a = a.replace(/^[A-Z]\s+(?=\d)/, "");                       // lonely letter before the house number
    var words = a.split(" ");
    while (words.length > 3 && !/[A-Z]{3,}|\d{3,}/.test(words[words.length - 1])) { words.pop(); }   // junk at the end
    return words.join(" ");
}

function findName(lines, text) {
    var pos = findNamePositional(lines);
    if (nameQuality(pos)) { return pos; }
    var other = findNameByLabels(lines, text);
    return nameQuality(other) ? other : "";
}

function findNameByLabels(lines, text) {
    var stop = /(BIRTH|ADDRESS|SEX|MARITAL|CITIZEN|SIGNATURE|NATIONALITY|TIRAHAN|PETSA)/i;
    // driver's license: "LAST NAME, FIRST NAME, MIDDLE NAME" label, the value is on the next line
    for (var i = 0; i < lines.length; i++) {
        if (/LAST\s*NAME\s*,\s*FIRST/i.test(lines[i]) && i + 1 < lines.length) {
            var parts = lines[i + 1].split(",");
            if (parts.length >= 2) {
                return onlyLetters(parts.slice(1).join(" ") + " " + parts[0]);
            }
        }
    }

    var last = valueAfter(lines, /(APELYIDO\s*\/?\s*)?(LAST\s*NAME|SURNAME)/i, stop);
    var given = valueAfter(lines, /(MGA\s*PANGALAN\s*\/?\s*)?GIVEN\s*NAMES?(\s*\(S\))?/i, stop);
    var middle = valueAfter(lines, /(GITNANG\s*APELYIDO\s*\/?\s*)?MIDDLE\s*NAME/i, stop);

    // passport machine readable line  P<PHLDELA<CRUZ<<JUAN<SANTOS<<<
    var mrz = text.toUpperCase().replace(/\s/g, "").match(/P[<K]PHL([A-Z]+(?:<[A-Z]+)*)<<([A-Z]+(?:<[A-Z]+)*)/);
    if (!last && !given && mrz) {
        return onlyLetters(mrz[2].replace(/</g, " ") + " " + mrz[1].replace(/</g, " "));
    }
    if (last || given) {
        return onlyLetters(given + " " + middle + " " + last);
    }
    // last try: first line that looks like "DELA CRUZ, JUAN SANTOS"
    for (var j = 0; j < lines.length; j++) {
        if (/^[A-Z\u00D1.' -]{2,},\s*[A-Z\u00D1.' -]{2,}$/.test(cleanLine(lines[j]))) {
            var p = cleanLine(lines[j]).split(",");
            return onlyLetters(p[1] + " " + p[0]);
        }
    }
    return "";
}

function findAddress(lines) {
    var stop = /(BIRTH|SEX|NAME|EXPIR|VALID|BLOOD|NATIONALITY|SIGNATURE|DATE|\bNO\b)/i;
    for (var i = 0; i < lines.length; i++) {
        var m = lines[i].match(/(TIRAHAN\s*\/?\s*)?ADDRESS/i);
        if (m) {
            var addr = cleanLine(lines[i].substring(m.index + m[0].length).replace(/^[\s:\/.-]+/, ""));
            var k = i + 1;
            var taken = 0;
            while (k < lines.length && taken < 2) {
                var nx = cleanLine(lines[k]);
                if (nx.length < 3 || stop.test(nx)) { break; }
                addr = (addr + " " + nx).trim();
                k++; taken++;
            }
            addr = addr.toUpperCase();
            // OCR noise after the zip code (example "4227 GC {") is removed
            var zip = addr.match(/^(.*\b\d{4})\b[^0-9]{0,8}$/);
            if (zip) { addr = zip[1]; }
            addr = addr.replace(/[^A-Z0-9\u00D1 .,#\/-]/g, "").replace(/\s+/g, " ").trim();
            addr = cleanAddress(addr);
            if (addr.length >= 6) { return addr; }
        }
    }
    return "";
}

function findIdNumber(text, type) {
    var def = ID_TYPES[type];
    if (!def) { return ""; }
    var up = text.toUpperCase();
    // OCR often reads O as 0 and I/L as 1 inside numbers
    if (type == "philsys" || type == "umid") {
        up = up.replace(/(?<=\d)[O]/g, "0").replace(/[O](?=\d)/g, "0").replace(/(?<=\d)[IL]/g, "1");
    }
    var m = up.match(def.pattern);
    if (!m) { return ""; }
    if (type == "test") {
        // choose the longest token that has numbers in it
        var all = up.match(/\b[A-Z0-9][A-Z0-9-]{5,19}\b/g) || [];
        var best = "";
        for (var i = 0; i < all.length; i++) { if (/\d/.test(all[i]) && all[i].length > best.length) { best = all[i]; } }
        return best;
    }
    var parts = m.slice(1);
    return parts.join(def.joiner);
}

// text after a label, plus the next line (dates are often on the line under the label)
function textAfterLabel(lines, labelRegex) {
    for (var i = 0; i < lines.length; i++) {
        var m = lines[i].match(labelRegex);
        if (m) {
            var rest = lines[i].substring(m.index + m[0].length);
            if (i + 1 < lines.length) { rest = rest + " " + lines[i + 1]; }
            return rest;
        }
    }
    return "";
}

// main function: returns the fields and which ones were found
function parseIdText(text, type) {
    var lines = text.split(/\r?\n/).map(cleanLine).filter(function (l) { return l.length > 0; });
    var result = { name: "", dob: "", address: "", idNumber: "", expiry: "" };

    result.name = findName(lines, text);
    result.address = findAddress(lines);
    result.idNumber = findIdNumber(text, type);

    // dates: use the label when there is one, otherwise guess
    var dobLine = textAfterLabel(lines, /BIRTH/i);
    var dobDates = findDates(dobLine);
    var expLine = textAfterLabel(lines, /(EXPIR|VALID\s*UNTIL|VALIDITY)/i);
    var expDates = findDates(expLine);
    var all = findDates(text).sort();

    if (dobDates.length > 0) { result.dob = dobDates[0]; }
    if (expDates.length > 0) { result.expiry = expDates[0]; }
    if (result.dob == "" && all.length > 0) { result.dob = all[0]; }   // the oldest date is the birth date
    return result;
}

// age from a "YYYY-MM-DD" date
function calcAge(dob) {
    var p = dob.split("-");
    if (p.length != 3) { return ""; }
    var today = new Date();
    var age = today.getFullYear() - parseInt(p[0], 10);
    var monthDiff = (today.getMonth() + 1) - parseInt(p[1], 10);
    if (monthDiff < 0 || (monthDiff == 0 && today.getDate() < parseInt(p[2], 10))) { age--; }
    return age;
}

// ---------- does the uploaded card match the ID type that was chosen? ----------
// every ID has its own title words. We look for them in the text that was read.
var ID_WORDS = {
    philsys: /PAGKAKA|LANLAN|IDENTIF|PHILSYS|PAMBANSANG/,
    passport: /PASSPORT|PASAPORTE|P<PHL/,
    license: /DRIVER.{0,3}S\s*LICENSE|LAND\s*TRANSPORT|\bLTO\b|DEPARTMENT\s*OF\s*TRANSPORT/,
    umid: /UNIFIED\s*MULTI|\bUMID\b|\bCRN\b/
};

// returns { ok: true/false, message: "..." }
function checkIdType(text, type) {
    if (type == "test" || !ID_WORDS[type]) { return { ok: true, message: "" }; }
    var up = text.toUpperCase();
    if (ID_WORDS[type].test(up)) { return { ok: true, message: "" }; }

    var chosen = ID_TYPES[type].name;
    for (var other in ID_WORDS) {
        if (other != type && ID_WORDS[other].test(up)) {
            return { ok: false, message: "This looks like a " + ID_TYPES[other].name + ", but you chose " + chosen + ". Choose the correct ID type (use the change link) or upload the correct ID." };
        }
    }
    return { ok: false, message: "This does not look like a " + chosen + ". Upload the front of that ID with its title visible (all four corners, no glare), or choose the correct ID type." };
}

// ---------- helpers for the PhilSys card (the values are printed big and in CAPITAL letters) ----------

// 16 digits read from a small picture that has digits only
function numberFromDigits(text) {
    var lines = text.split(/\r?\n/);
    for (var i = 0; i < lines.length; i++) {
        var d = lines[i].replace(/\D/g, "");
        if (d.length >= 16) {
            for (var s = 0; s + 16 <= d.length; s++) {
                var c = d.substr(s, 16);
                return c.substr(0, 4) + "-" + c.substr(4, 4) + "-" + c.substr(8, 4) + "-" + c.substr(12, 4);
            }
        }
    }
    var all = text.replace(/\D/g, "");
    if (all.length >= 16) { return all.substr(0, 4) + "-" + all.substr(4, 4) + "-" + all.substr(8, 4) + "-" + all.substr(12, 4); }
    return "";
}

// last name, given names, middle name are the CAPITAL lines (the small labels have small letters)
function philsysNameFromLines(text) {
    var skip = /(REPUBLI|PAMBANSANG|PILIPINAS|PHILIPPINES|IDENTIFICATION|PAGKAKILANLAN|PHL|NAME|ADDRESS|BIRTH|LAST|GIVEN|MIDDLE|TIRAHAN|PETSA|APELYIDO|PANGALAN)/;
    var good = [];
    var lines = text.split(/\r?\n/);
    for (var i = 0; i < lines.length; i++) {
        var l = cleanLine(lines[i].replace(/[^A-Za-z\u00D1\u00F1.' -]/g, " "));
        if (l.length < 3 || l != l.toUpperCase() || skip.test(l)) { continue; }
        if (l.replace(/[^A-Z\u00D1]/g, "").length < 3) { continue; }
        good.push(l);
    }
    if (good.length >= 3) { return good[1] + " " + good[2] + " " + good[0]; }   // given + middle + last
    if (good.length == 2) { return good[1] + " " + good[0]; }
    return "";
}

// does the name look like a real name? (rejects garbage like "L LS IT BI ETE")
function nameLooksOk(name) {
    var words = name.split(/\s+/).filter(function (w) { return w.length > 0; });
    if (words.length < 2) { return false; }
    var longWord = false, single = 0;
    for (var i = 0; i < words.length; i++) {
        if (words[i].replace(/[^A-Z\u00D1]/gi, "").length >= 4) { longWord = true; }
        if (words[i].replace(/[^A-Z\u00D1]/gi, "").length == 1) { single++; }
    }
    return longWord && single <= 1;
}

if (typeof module !== "undefined") { module.exports = { checkIdType: checkIdType, numberFromDigits: numberFromDigits, philsysNameFromLines: philsysNameFromLines, nameLooksOk: nameLooksOk, parseIdText: parseIdText, calcAge: calcAge, findDates: findDates, ID_TYPES: ID_TYPES }; }
