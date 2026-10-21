<?php
/* Șabloane implicite de documente – conținut propriu BENIAL CONSULTING, redactat pe baza
   Legii 319/2006 + HG 1425/2006 (SSM) și Legii 307/2006, Legii 481/2004, OMAI 163/2007, OMAI 712/2005 (SU/PSI).
   Format conținut tematici: o temă pe linie, „Titlu temă | ore”. */
function default_templates(): array {
    $T = [];
    $T[] = ['SSM-TEM-IIG', 'Tematica instruirii introductiv-generale SSM', 'SSM', 'tematica', 'Introductiv-generală', "Cadrul legal SSM: Legea 319/2006, HG 1425/2006, obligațiile angajatorului și ale lucrătorului | 1
Răspunderi juridice (disciplinară, contravențională, civilă, penală) pentru nerespectarea normelor SSM | 0.5
Noțiuni despre accidente de muncă și boli profesionale; cauze, consecințe, comunicare și cercetare | 1
Riscurile specifice activității firmei și măsurile generale de prevenire și protecție | 1
Echipamentul individual de protecție: acordare, purtare, întreținere, înlocuire | 0.5
Semnalizarea de securitate și sănătate la locul de muncă | 0.5
Supravegherea sănătății lucrătorilor – examenele de medicina muncii | 0.5
Organizarea primului ajutor și conduita în caz de accident; numerele de urgență (112) | 1
Noțiuni privind situațiile de urgență, evacuarea și căile de evacuare | 1
Verificarea cunoștințelor (test) | 1"];
    $T[] = ['SSM-TEM-ILM', 'Tematica instruirii la locul de muncă SSM', 'SSM', 'tematica', 'La locul de muncă', "Prezentarea locului de muncă, a echipamentelor de muncă și a fluxului de lucru | 1
Riscurile identificate la postul de lucru (conform evaluării riscurilor) și măsurile de prevenire | 1
Instrucțiunile proprii de SSM pentru activitatea/postul desfășurat | 1.5
Utilizarea corectă a echipamentelor de muncă și a dispozitivelor de protecție | 1
EIP specific postului și modul de verificare înainte de utilizare | 0.5
Proceduri de lucru sigure, interdicții și comunicarea situațiilor periculoase | 1
Demonstrații practice și exersarea operațiilor la postul de lucru | 1
Verificarea cunoștințelor (test și verificare practică) | 1"];
    $T[] = ['SSM-TEM-IP', 'Tematica instruirii periodice SSM', 'SSM', 'tematica', 'Periodică', "Reîmprospătarea cunoștințelor din instrucțiunile proprii de SSM ale postului | 0.5
Modificări legislative și ale evaluării riscurilor de la ultima instruire | 0.5
Analiza incidentelor, accidentelor și a situațiilor periculoase apărute | 0.5
Utilizarea și întreținerea EIP; verificarea echipamentelor de muncă | 0.5
Primul ajutor și conduita în caz de urgență | 0.5
Verificarea cunoștințelor (test) | 0.5"];
    $T[] = ['SSM-FIS-ANG', 'Fișă individuală de instructaj SSM – la angajare', 'SSM', 'fisa', 'La angajare (introductiv-general + la locul de muncă)', ''];
    $T[] = ['SSM-FIS-PER', 'Fișă individuală de instructaj SSM – periodic', 'SSM', 'fisa', 'Periodică', ''];
    $T[] = ['SSM-FIS-SUP', 'Fișă individuală de instructaj SSM – periodic suplimentar', 'SSM', 'fisa', 'Suplimentară', "Motivul instruirii suplimentare: reluarea activității după o absență mai mare de 6 luni / modificarea procedurilor sau a echipamentelor / producerea unui accident sau incident / modificarea legislației / alte situații dispuse de angajator."];
    $T[] = ['SSM-TEST', 'Rezultatul testării cunoștințelor SSM (fișa 1.6)', 'SSM', 'test', 'Testare', ''];
    $T[] = ['SU-TEM-IIG', 'Tematica instructajului introductiv general SU/PSI', 'SU', 'tematica', 'Introductiv-general', "Legislația privind apărarea împotriva incendiilor și protecția civilă (Legea 307/2006, Legea 481/2004, OMAI 163/2007) | 1
Obligațiile angajaților privind prevenirea și stingerea incendiilor | 0.5
Cauze frecvente de incendiu și măsuri de prevenire specifice firmei | 1
Mijloacele tehnice de stingere din dotare: tipuri, amplasare, mod de utilizare | 1
Planul de evacuare, căile de evacuare și locul de adunare | 0.5
Alarmarea, anunțarea serviciilor de urgență (112) și conduita în caz de incendiu, cutremur, inundație | 1
Regimul fumatului, al focului deschis și al lucrărilor cu pericol de incendiu | 0.5
Verificarea cunoștințelor (test) | 0.5"];
    $T[] = ['SU-TEM-ILM', 'Tematica instructajului specific locului de muncă SU/PSI', 'SU', 'tematica', 'Specific locului de muncă', "Riscurile de incendiu ale locului de muncă și materialele combustibile existente | 1
Măsuri de prevenire la utilizarea instalațiilor electrice și a echipamentelor | 0.5
Amplasarea și utilizarea stingătoarelor și hidranților din zona de lucru | 1
Traseul de evacuare din locul de muncă și sarcinile în caz de urgență | 0.5
Exercițiu practic de utilizare a stingătorului / de evacuare | 1"];
    $T[] = ['SU-TEM-IP', 'Tematica instructajului periodic SU/PSI', 'SU', 'tematica', 'Periodic', "Reîmprospătarea regulilor de prevenire a incendiilor la locul de muncă | 0.5
Verificarea stării mijloacelor de stingere și a căilor de evacuare | 0.5
Conduita în situații de urgență: incendiu, cutremur, inundație, accident chimic | 0.5
Concluzii din exercițiile de evacuare și din evenimentele produse | 0.5"];
    $T[] = ['SU-FIS-ANG', 'Fișă individuală de instruire SU/PSI – la angajare', 'SU', 'fisa', 'La angajare (introductiv general + specific locului de muncă)', ''];
    $T[] = ['SU-FIS-PER', 'Fișă individuală de instruire SU/PSI – periodic', 'SU', 'fisa', 'Periodic', ''];
    $T[] = ['SU-FIS-LP', 'Fișă de instructaj SU/PSI – lucrări periculoase', 'SU', 'fisa', 'Specific pentru lucrări periculoase', "Lucrarea: lucrări cu foc deschis / sudură / tăiere / lucrări în spații cu pericol de explozie (se completează).
Măsuri obligatorii: obținerea permisului de lucru cu foc, îndepărtarea materialelor combustibile, mijloace de stingere la îndemână, supraveghere pe durata lucrării și verificarea zonei după terminare."];
    $T[] = ['SU-TEST', 'Rezultatul testării cunoștințelor SU/PSI', 'SU', 'test', 'Testare', ''];
    return $T;
}
