/* Data for the game lobby. Questions come from the HK6002 syllabus banks (weekly-tests.js and the main quiz);
   everything else below is short, syllabus-based material for the individual games. */
(function () {
    'use strict';

    const CATEGORY_BY_WEEK = {
        1: 'Ancient Kazakhstan', 2: 'Ancient Kazakhstan', 3: 'Turkic Khaganates', 4: 'Golden Horde',
        5: 'Kazakh Khanate', 6: 'Russian Empire Period', 7: 'Alash Movement', 8: 'Important Dates',
        9: 'Soviet Kazakhstan', 10: 'Soviet Kazakhstan', 11: 'Soviet Kazakhstan', 12: 'Soviet Kazakhstan',
        13: 'Independent Kazakhstan', 14: 'Independent Kazakhstan', 15: 'Independent Kazakhstan'
    };

    function shuffle(list) {
        const a = list.slice();
        for (let i = a.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [a[i], a[j]] = [a[j], a[i]];
        }
        return a;
    }

    /* ---------- Question database: only short questions, so they can be read at game speed ---------- */
    let cache = null;

    function isShort(item) {
        return item.question.length <= 82 && [item.correctAnswer, ...item.wrongAnswers].every(a => a.length <= 38 && a.indexOf(';') === -1);
    }

    function buildQuestions() {
        const list = [];
        if (typeof WEEKLY_TESTS !== 'undefined') {
            Object.keys(WEEKLY_TESTS).forEach(w => {
                const week = WEEKLY_TESTS[w];
                week.questions.forEach((item, i) => {
                    if (!isShort(item)) return;
                    const position = i / week.questions.length;
                    list.push({
                        id: 'w' + w + '-' + i,
                        week: Number(w),
                        category: CATEGORY_BY_WEEK[w] || 'Important Dates',
                        difficulty: position < 0.4 ? 'easy' : (position < 0.75 ? 'medium' : 'hard'),
                        question: item.question,
                        correctAnswer: item.correctAnswer,
                        wrongAnswers: item.wrongAnswers,
                        hint: 'Week ' + w + ': ' + week.title
                    });
                });
            });
        }
        if (typeof fullQuizPool !== 'undefined') {
            fullQuizPool.forEach((item, i) => {
                if (!isShort(item)) return;
                const early = i < 63;
                list.push({
                    id: 'q' + i,
                    week: early ? 1 : 3,
                    category: early ? 'Ancient Kazakhstan' : 'Turkic Khaganates',
                    difficulty: i % 3 === 0 ? 'easy' : (i % 3 === 1 ? 'medium' : 'hard'),
                    question: item.question,
                    correctAnswer: item.correctAnswer,
                    wrongAnswers: item.wrongAnswers,
                    hint: early ? 'Weeks 1-2: prehistory and the Early Iron Age' : 'Week 3: the Turkic era'
                });
            });
        }
        return list;
    }

    /* Returns n questions (fresh copies, answers shuffled): { question, answers[4], correct (index), hint, ... } */
    function pickQuestions(n, options) {
        options = options || {};
        if (!cache) cache = buildQuestions();
        let pool = cache;
        if (options.difficulty) {
            const narrowed = pool.filter(q => q.difficulty === options.difficulty);
            if (narrowed.length >= n) pool = narrowed;
        }
        const exclude = options.exclude || [];
        pool = pool.filter(q => exclude.indexOf(q.id) === -1);
        return shuffle(pool).slice(0, n).map(q => {
            const answers = shuffle([q.correctAnswer, ...q.wrongAnswers]);
            return {
                id: q.id, week: q.week, category: q.category, difficulty: q.difficulty, hint: q.hint,
                question: q.question, answers, correct: answers.indexOf(q.correctAnswer),
                explanation: 'Correct answer: ' + q.correctAnswer
            };
        });
    }

    /* ---------- Timeline Rush: y = year, t = short event name ---------- */
    const timelineEvents = [
        { y: 552, t: 'Turkic Khaganate founded' }, { y: 751, t: 'Battle of Talas' },
        { y: 1206, t: 'Genghis Khan proclaimed' }, { y: 1219, t: 'Mongols attack Otrar' },
        { y: 1428, t: 'Abulkhair becomes khan' }, { y: 1465, t: 'Kazakh Khanate founded' },
        { y: 1511, t: 'Kasym Khan takes power' }, { y: 1643, t: 'Battle of Orbulak' },
        { y: 1723, t: 'Aktaban Shubyryndy' }, { y: 1731, t: 'Junior Zhuz seeks Russia' },
        { y: 1771, t: 'Ablai proclaimed khan' }, { y: 1783, t: 'Syrym Datuly uprising' },
        { y: 1822, t: 'Charter ends khan rule' }, { y: 1837, t: 'Kenesary uprising begins' },
        { y: 1854, t: 'Verny fortress founded' }, { y: 1867, t: 'Temporary Regulations' },
        { y: 1891, t: 'Steppe Regulations' }, { y: 1905, t: 'Karkaraly Petition' },
        { y: 1916, t: 'Central Asian uprising' }, { y: 1917, t: 'Alash Autonomy proclaimed' },
        { y: 1920, t: 'Kazakh ASSR created' }, { y: 1929, t: 'Almaty becomes capital' },
        { y: 1930, t: 'Turksib railway opens' }, { y: 1931, t: 'Great famine begins' },
        { y: 1936, t: 'Kazakh SSR formed' }, { y: 1937, t: 'Great Terror begins' },
        { y: 1941, t: 'Great Patriotic War begins' }, { y: 1946, t: 'Academy of Sciences founded' },
        { y: 1949, t: 'First nuclear test' }, { y: 1954, t: 'Virgin Lands campaign' },
        { y: 1961, t: 'Gagarin flies from Baikonur' }, { y: 1969, t: 'Golden Man found' },
        { y: 1986, t: 'December events in Almaty' }, { y: 1990, t: 'Declaration of Sovereignty' },
        { y: 1991, t: 'Independence declared' }, { y: 1993, t: 'Tenge introduced' },
        { y: 1995, t: 'New Constitution' }, { y: 1997, t: 'Capital moves to Astana' },
        { y: 2003, t: 'First Congress of Religions' }, { y: 2015, t: 'Eurasian Economic Union starts' },
        { y: 2019, t: 'Tokayev becomes President' }, { y: 2022, t: 'Constitutional referendum' }
    ];

    /* ---------- History Map: real coordinates (lon, lat), projected by the game ---------- */
    const mapLocations = [
        { id: 'botai', name: 'Botai', lon: 68.0, lat: 53.0, q: 'Early horse herding: the Botai culture', info: 'Botai: horses were herded here 5,000 years ago.' },
        { id: 'issyk', name: 'Issyk', lon: 77.5, lat: 43.35, q: 'Where the Golden Man was found', info: 'Issyk kurgan, found in 1969.' },
        { id: 'tamgaly', name: 'Tamgaly', lon: 75.5, lat: 43.8, q: 'Bronze Age petroglyphs (UNESCO)', info: 'Tamgaly: rock art, UNESCO since 2004.' },
        { id: 'berel', name: 'Berel', lon: 86.2, lat: 49.35, q: 'Berel kurgans with frozen horses', info: 'Berel: Saka tombs in the Altai.' },
        { id: 'zhezkazgan', name: 'Zhezkazgan', lon: 67.7, lat: 47.8, q: 'Ancient copper mines', info: 'Bronze Age copper mining area.' },
        { id: 'otrar', name: 'Otrar', lon: 68.3, lat: 42.85, q: 'Silk Road city, al-Farabi\'s Farab', info: 'Otrar: besieged by the Mongols in 1219.' },
        { id: 'turkistan', name: 'Turkistan', lon: 68.25, lat: 43.3, q: 'Yasawi mausoleum', info: 'Turkistan: a spiritual centre of the khanate.' },
        { id: 'taraz', name: 'Taraz', lon: 71.4, lat: 42.9, q: 'Battle of Talas (751)', info: 'Taraz: near the Talas river.' },
        { id: 'chu', name: 'Chu valley', lon: 73.6, lat: 43.4, q: 'Where Kerey and Janibek settled', info: 'Chu valley: cradle of the Kazakh Khanate.' },
        { id: 'astana', name: 'Astana', lon: 71.45, lat: 51.15, q: 'Kenesary took Akmola fort (1838)', info: 'Akmola, today\'s Astana.' },
        { id: 'almaty', name: 'Almaty', lon: 76.9, lat: 43.25, q: 'Verny fortress (1854)', info: 'Verny became Almaty.' },
        { id: 'karaganda', name: 'Karaganda', lon: 73.1, lat: 49.8, q: 'Karlag camps and coal', info: 'Karaganda: coal basin and Karlag.' },
        { id: 'semey', name: 'Semey', lon: 80.25, lat: 50.4, q: 'Alash-Orda government seat', info: 'Semey (Semipalatinsk).' },
        { id: 'test-site', name: 'Test site', lon: 78.5, lat: 50.6, q: 'Semipalatinsk nuclear test site', info: 'Closed in 1991.' },
        { id: 'baikonur', name: 'Baikonur', lon: 63.3, lat: 45.6, q: 'Gagarin\'s launch site (1961)', info: 'Baikonur Cosmodrome.' },
        { id: 'aral', name: 'Aral Sea', lon: 61.0, lat: 46.2, q: 'The shrinking sea', info: 'Aral: irrigation drained it.' },
        { id: 'kyzylorda', name: 'Kyzylorda', lon: 65.5, lat: 44.85, q: 'Capital of Kazakhstan 1925-1929', info: 'Kyzylorda.' },
        { id: 'turgai', name: 'Turgai', lon: 63.6, lat: 49.6, q: '1916 uprising: Amangeldy\'s region', info: 'Turgai: centre of the 1916 revolt.' },
        { id: 'karkaraly', name: 'Karkaraly', lon: 75.4, lat: 49.4, q: 'Karkaraly Petition (1905)', info: 'Karkaraly: 1905 petition.' },
        { id: 'oral', name: 'Oral', lon: 51.4, lat: 51.2, q: 'Ural (Zhaiyk) river city', info: 'Oral, on the Zhaiyk.' },
        { id: 'atyrau', name: 'Atyrau', lon: 51.9, lat: 47.1, q: 'Caspian oil city', info: 'Atyrau, at the Zhaiyk mouth.' },
        { id: 'aktau', name: 'Aktau', lon: 51.2, lat: 43.65, q: 'Caspian port of Mangystau', info: 'Aktau: Caspian convention (2018).' },
        { id: 'petropavl', name: 'Petropavlovsk', lon: 69.1, lat: 54.9, q: 'Trans-Siberian railway town in the north', info: 'Northern Kazakhstan.' },
        { id: 'ulytau', name: 'Ulytau', lon: 67.0, lat: 48.7, q: 'Heart of the steppe (Ulytau)', info: 'Ulytau: central Kazakhstan.' }
    ];

    /* Rough outline (lon, lat) drawn as the stylised Kazakhstan shape */
    const mapOutline = [
        [46.5, 48.5], [47.0, 50.0], [48.5, 50.6], [50.5, 51.6], [52.5, 51.5], [55.5, 50.6], [58.5, 51.1], [60.2, 50.7],
        [61.5, 51.5], [61.6, 53.0], [63.5, 54.0], [65.5, 54.6], [69.0, 55.4], [71.5, 54.4], [73.5, 53.9], [76.0, 54.1],
        [77.8, 53.3], [79.5, 51.4], [81.0, 50.8], [83.0, 51.1], [85.5, 49.6], [87.3, 49.1], [85.5, 47.3], [83.0, 47.2],
        [82.0, 45.3], [80.3, 44.9], [80.1, 42.7], [78.0, 42.9], [75.0, 42.9], [73.5, 42.5], [71.0, 42.8], [70.9, 42.3],
        [68.2, 41.9], [66.7, 42.0], [64.5, 43.5], [61.0, 44.4], [58.5, 45.4], [56.0, 45.0], [55.0, 44.0], [53.0, 42.4],
        [52.6, 41.3], [51.0, 44.0], [50.2, 44.6], [49.0, 46.4], [49.5, 46.9], [51.9, 47.0], [48.8, 47.4], [47.0, 47.5]
    ];

    /* ---------- Who Am I: name, years, one-line fact, four clues from broad to easy ---------- */
    const figures = [
        { name: 'Ablai Khan', years: '1711-1781', fact: 'Balanced Russia, China and the Dzungars.', clues: ['Born in 1711', 'Led the Kazakh Khanate', 'Proclaimed khan in 1771', 'United the three zhuzes'] },
        { name: 'Kasym Khan', years: 'ruled c. 1511-1518', fact: 'Author of the Qasqa Zholy.', clues: ['Ruled around 1511', 'Khanate grew to ~1 million people', 'His law code: Qasqa Zholy', 'Khan of the Kazakh peak'] },
        { name: 'Tauke Khan', years: 'ruled 1680-1718', fact: 'Zhety Zhargy was written under him.', clues: ['Ruled from 1680', 'Turkistan was his centre', 'Three biys advised him', 'Zhety Zhargy: seven laws'] },
        { name: 'Kenesary Kasymuly', years: '1802-1847', fact: 'Led the great 1837-1847 uprising.', clues: ['Grandson of Ablai', 'Rose against Russia in 1837', 'Proclaimed khan in 1841', 'Killed in 1847'] },
        { name: 'Abai Kunanbayev', years: '1845-1904', fact: 'Poet and thinker of the Kazakh steppe.', clues: ['Born in 1845', 'A great poet', 'Wrote the Book of Words', 'Lived near Semey'] },
        { name: 'Chokan Valikhanov', years: '1835-1865', fact: 'Explorer and ethnographer.', clues: ['Born in 1835', 'A scholar and traveller', 'Reached Kashgar in 1858', 'Died young in 1865'] },
        { name: 'Akhmet Baitursynov', years: '1872-1937', fact: 'Called the Teacher of the Nation.', clues: ['Born in 1872', 'Edited the Qazaq newspaper', 'Reformed the alphabet', 'Executed in 1937'] },
        { name: 'Alikhan Bokeikhanov', years: '1866-1937', fact: 'Led the Alash movement.', clues: ['Born in 1866', 'Signed the Vyborg Appeal', 'Led the Alash Party', 'Chaired Alash-Orda'] },
        { name: 'Al-Farabi', years: 'c. 872-950', fact: 'The Second Teacher after Aristotle.', clues: ['Lived in the 10th century', 'Born in Farab', 'Commented on Aristotle', 'Called the Second Teacher'] },
        { name: 'Genghis Khan', years: 'c. 1162-1227', fact: 'Founded the Mongol Empire.', clues: ['Born around 1162', 'Proclaimed in 1206', 'Attacked Khorezm in 1219', 'Died in 1227'] },
        { name: 'Batu Khan', years: 'c. 1205-1255', fact: 'Founder of the Golden Horde.', clues: ['Grandson of Genghis', 'Son of Juchi', 'Made Sarai his capital', 'Founded the Golden Horde'] },
        { name: 'Bumin Khan', years: 'died 552', fact: 'First khagan of the Turks.', clues: ['Lived in the 6th century', 'Ashina clan leader', 'Beat the Rouran', 'Founded the Turkic Khaganate'] },
        { name: 'Amangeldy Imanov', years: '1873-1919', fact: 'Leader of the 1916 revolt in Turgai.', clues: ['Born in 1873', 'Led the Turgai rebels', 'Rose up in 1916', 'Sided with the Reds'] },
        { name: 'Mustafa Shokay', years: '1890-1941', fact: 'Leader of the Turkestan Autonomy.', clues: ['Born in 1890', 'A Kazakh statesman', 'Led in Kokand', 'Autonomy fell in 1918'] },
        { name: 'Kanysh Satpayev', years: '1899-1964', fact: 'First president of the Academy of Sciences.', clues: ['Born in 1899', 'A famous geologist', 'Found copper riches', 'Led the Academy from 1946'] },
        { name: 'Mukhtar Auezov', years: '1897-1961', fact: 'Author of the novel Abai Zholy.', clues: ['Born in 1897', 'A great writer', 'Wrote Enlik-Kebek', 'Wrote Abai Zholy'] },
        { name: 'Olzhas Suleimenov', years: 'born 1936', fact: 'Founded the Nevada-Semipalatinsk movement.', clues: ['Born in 1936', 'A poet and public figure', 'Wrote Az i Ya', 'Stopped the nuclear tests'] },
        { name: 'Bauyrzhan Momyshuly', years: '1910-1982', fact: 'Panfilov\'s officer, hero of the war.', clues: ['Born in 1910', 'A Soviet officer', 'Fought at Moscow in 1941', 'Featured in Volokolamsk Highway'] },
        { name: 'Nursultan Nazarbayev', years: 'born 1940', fact: 'First President of Kazakhstan.', clues: ['Born in 1940', 'Party leader in 1989', 'Elected in 1991', 'Moved the capital to Astana'] },
        { name: 'Kassym-Jomart Tokayev', years: 'born 1953', fact: 'President since 2019.', clues: ['Born in 1953', 'A career diplomat', 'President from 2019', 'Leads New Kazakhstan reforms'] }
    ];

    /* ---------- True or Trap: t = true/false, e = short explanation shown after the answer ---------- */
    const statements = [
        { s: 'The Kazakh Khanate was founded around 1465.', t: true, e: 'Kerey and Janibek, about 1465.' },
        { s: 'The Battle of Talas took place in 751.', t: true, e: 'Arabs beat the Tang in 751.' },
        { s: 'Kasym Khan\'s law code is called Qasqa Zholy.', t: true, e: 'Qasqa Zholy: Kasym Khan.' },
        { s: 'Tauke Khan is linked with Zhety Zhargy.', t: true, e: 'Zhety Zhargy: Tauke\'s laws.' },
        { s: 'Sarai was the capital of the Golden Horde.', t: true, e: 'Batu built Sarai on the Volga.' },
        { s: 'Abai wrote the Book of Words.', t: true, e: 'Qara sozder is Abai\'s work.' },
        { s: 'The Alash-Orda government sat in Semey.', t: true, e: 'Semipalatinsk was its seat.' },
        { s: 'The 1916 uprising began after a conscription decree.', t: true, e: 'Men were called up for rear labour.' },
        { s: 'The Baikonur Cosmodrome is in Kazakhstan.', t: true, e: 'Gagarin launched from there.' },
        { s: 'Kazakhstan declared independence on 16 December 1991.', t: true, e: 'Independence Day: 16 December.' },
        { s: 'The tenge was introduced in 1993.', t: true, e: 'National currency: 1993.' },
        { s: 'The Botai culture is linked to early horse herding.', t: true, e: 'Botai: horses, about 3700 BC.' },
        { s: 'The Issyk Golden Man was found in 1969.', t: true, e: 'Excavated by Kemal Akishev.' },
        { s: 'Al-Farabi is called the Second Teacher.', t: true, e: 'After Aristotle.' },
        { s: 'Kenesary Kasymuly was Ablai Khan\'s grandson.', t: true, e: 'His uprising: 1837-1847.' },
        { s: 'The Turksib railway connects Turkestan and Siberia.', t: true, e: 'Built 1927-1930.' },
        { s: 'The Virgin Lands campaign began in 1954.', t: true, e: 'Khrushchev\'s campaign.' },
        { s: 'Astana became the capital in 1997.', t: true, e: 'Moved from Almaty in 1997.' },
        { s: 'Kutadgu Bilig was written by Yusuf Balasaguni.', t: true, e: 'Completed about 1069-1070.' },
        { s: 'The Yasawi mausoleum stands in Turkistan.', t: true, e: 'Built under Timur.' },
        { s: 'The Kazakh Khanate was founded in 1731.', t: false, e: 'It was about 1465.' },
        { s: 'The Battle of Talas took place in 1219.', t: false, e: 'Talas was 751; 1219 is Otrar.' },
        { s: 'Qasqa Zholy was created by Tauke Khan.', t: false, e: 'It was Kasym Khan.' },
        { s: 'Sarai was the capital of the Turkic Khaganate.', t: false, e: 'Sarai: Golden Horde.' },
        { s: 'Abai founded the Alash Party.', t: false, e: 'Bokeikhanov led Alash.' },
        { s: 'The Alash-Orda government sat in Almaty.', t: false, e: 'It sat in Semey.' },
        { s: 'Kenesary\'s uprising ended in 1916.', t: false, e: 'It ended in 1847.' },
        { s: 'Almaty became the capital in 1997.', t: false, e: 'Astana did.' },
        { s: 'Kazakhstan declared independence in 1985.', t: false, e: 'It was 1991.' },
        { s: 'The tenge was introduced in 1961.', t: false, e: 'It was 1993.' },
        { s: 'The Saka built the Great Wall of China.', t: false, e: 'China built its own wall.' },
        { s: 'Ablai Khan lived in the 12th century.', t: false, e: 'He lived 1711-1781.' },
        { s: 'Semipalatinsk was a space launch site.', t: false, e: 'It was a nuclear test site.' },
        { s: 'The Virgin Lands campaign began in 1929.', t: false, e: 'It began in 1954.' },
        { s: 'Al-Farabi wrote Kutadgu Bilig.', t: false, e: 'Yusuf Balasaguni did.' },
        { s: 'Genghis Khan died in 1465.', t: false, e: 'He died in 1227.' },
        { s: 'The Golden Man was found near Astana.', t: false, e: 'Near Almaty (Issyk).' },
        { s: 'Batu and Berke founded the Kazakh Khanate.', t: false, e: 'Kerey and Janibek did.' },
        { s: 'The Great Terror peaked in 1917.', t: false, e: 'It peaked in 1937-1938.' },
        { s: 'Tokayev became President in 1991.', t: false, e: 'In 2019.' }
    ];

    window.GameData = { shuffle, pickQuestions, timelineEvents, mapLocations, mapOutline, figures, statements };
})();
