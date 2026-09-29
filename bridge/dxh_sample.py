"""De-identified replay of the observed record layout; commissioning only."""
RESULTS = [
('WBC','8.52','R','x10e3/uL','3.71 to 10.67'),
('RBC','4.61','R','x10e6/uL','3.99 to 5.2'),
('HGB','11.57','Rl','g/dL','12 to 16.5'),
('HCT','37.0','R','%','35.1 to 49.9'),
('MCV','80.2','R','fL','78.4 to 97.6'),
('MCH','25.1','Rl','pg','26.5 to 33.5'),
('MCHC','31.3','Rl','g/dL','32.9 to 35.4'),
('RDW','17.3','Rh','%','10 to 14.6'),
('RDW-SD','46.1','R','fL','38.9 to 49'),
('PLT','275.9','R','x10e3/uL','150 to 400'),
('MPV','7.43','R','fL','7.42 to 10'),
('@PCT','0.205','R','%','0 to 9.999'),
('@PDW','23.8','R','','0 to 99.9'),
('@LHD','30.4','R','%','0 to 100'),
('@MAF','9.3','R','','0 to 99.9'),
('LY','34.33','R','%','20 to 38'),
('MO','7.97','R','%','2 to 20'),
('NE','56.57','R','%','40 to 80'),
('EO','0.92','Rl','%','2 to 7.5'),
('BA','0.21','R','%','0 to 1'),
('LY#','2.92','R','x10e3/uL','1 to 3'),
('MO#','0.68','R','x10e3/uL','0.25 to 0.99'),
('NE#','4.82','R','x10e3/uL','3 to 7'),
('EO#','0.08','R','x10e3/uL','0.04 to 0.48'),
('BA#','0.02','R','x10e3/uL','0 to 0.03'),
('@IMM','0.94','R','%','0 to 100'),
('@IMM#','0.08','R','x10e3/uL','0 to 150')]

def sample():
    records = ['H|\\!~|||DxH 500!01|||||||P|LIS2-A2|20000101120000',
               'P|1||!||!!!!||!!!!|U!||||||||||||||||||||||||||',
               'O|1|TEST-SAMPLE!|TEST-RUN!OV|!!!CD|||||||||||WB|!!!!||||||||||||||',
               'C|1|I|Lyse Expired|I!Y', 'C|2|I|Suspect Diff|I!U']
    for i,(code,value,flag,unit,reference) in enumerate(RESULTS,1):
        records.append(f'R|{i}|!!!{code}|{value}  ! {flag} |{unit}||{reference}|A||||DXH500||20000101115900|01')
        if code.startswith('@'):
            records.append('C|1|I|Test names beginning with @ are for research use only. Not for use in diagnostics procedures|G!')
    return ('\r'.join(records + ['L|1|N']) + '\r').encode('ascii')
