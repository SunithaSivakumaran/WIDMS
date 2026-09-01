USE widms;
SET @geo_admin := (SELECT id FROM users WHERE role='admin' ORDER BY id LIMIT 1);
INSERT INTO districts(name,status,created_by) VALUES ('Galle','active',@geo_admin),('Matara','active',@geo_admin),('Hambantota','active',@geo_admin) ON DUPLICATE KEY UPDATE status='active';
INSERT INTO ds_divisions(district_id,name,status,created_by)
SELECT d.id,n.name,'active',@geo_admin FROM districts d JOIN (
 SELECT 'Galle' district,'Akmeemana' name UNION ALL SELECT 'Galle','Ambalangoda' UNION ALL SELECT 'Galle','Baddegama' UNION ALL SELECT 'Galle','Balapitiya' UNION ALL SELECT 'Galle','Bentota' UNION ALL SELECT 'Galle','Bope-Poddala' UNION ALL SELECT 'Galle','Elpitiya' UNION ALL SELECT 'Galle','Galle Four Gravets' UNION ALL SELECT 'Galle','Gonapinuwala' UNION ALL SELECT 'Galle','Habaraduwa' UNION ALL SELECT 'Galle','Imaduwa' UNION ALL SELECT 'Galle','Karandeniya' UNION ALL SELECT 'Galle','Nagoda' UNION ALL SELECT 'Galle','Neluwa' UNION ALL SELECT 'Galle','Niyagama' UNION ALL SELECT 'Galle','Thawalama' UNION ALL SELECT 'Galle','Welivitiya-Divithura' UNION ALL SELECT 'Galle','Yakkalamulla' UNION ALL
 SELECT 'Matara','Akuressa' UNION ALL SELECT 'Matara','Athuraliya' UNION ALL SELECT 'Matara','Devinuwara' UNION ALL SELECT 'Matara','Dickwella' UNION ALL SELECT 'Matara','Hakmana' UNION ALL SELECT 'Matara','Kamburupitiya' UNION ALL SELECT 'Matara','Kirinda Puhulwella' UNION ALL SELECT 'Matara','Kotapola' UNION ALL SELECT 'Matara','Malimbada' UNION ALL SELECT 'Matara','Matara Four Gravets' UNION ALL SELECT 'Matara','Mulatiyana' UNION ALL SELECT 'Matara','Pasgoda' UNION ALL SELECT 'Matara','Pitabeddara' UNION ALL SELECT 'Matara','Thihagoda' UNION ALL SELECT 'Matara','Weligama' UNION ALL SELECT 'Matara','Welipitiya' UNION ALL
 SELECT 'Hambantota','Ambalantota' UNION ALL SELECT 'Hambantota','Angunakolapelessa' UNION ALL SELECT 'Hambantota','Beliatta' UNION ALL SELECT 'Hambantota','Hambantota' UNION ALL SELECT 'Hambantota','Katuwana' UNION ALL SELECT 'Hambantota','Lunugamvehera' UNION ALL SELECT 'Hambantota','Okewela' UNION ALL SELECT 'Hambantota','Sooriyawewa' UNION ALL SELECT 'Hambantota','Tangalle' UNION ALL SELECT 'Hambantota','Tissamaharama' UNION ALL SELECT 'Hambantota','Walasmulla' UNION ALL SELECT 'Hambantota','Weeraketiya'
) n ON n.district=d.name ON DUPLICATE KEY UPDATE status='active';
INSERT INTO gn_divisions(ds_division_id,name,status,created_by)
SELECT ds.id,'Other / Not Listed','active',@geo_admin FROM ds_divisions ds JOIN districts d ON d.id=ds.district_id WHERE d.name IN ('Galle','Matara','Hambantota')
ON DUPLICATE KEY UPDATE status='active';

INSERT INTO gn_divisions(ds_division_id,name,status,created_by)
SELECT ds.id,sample.name,'active',@geo_admin
FROM ds_divisions ds
JOIN districts d ON d.id=ds.district_id
CROSS JOIN (
 SELECT 'Sample GN Division 1' name
 UNION ALL SELECT 'Sample GN Division 2'
) sample
WHERE d.name IN ('Galle','Matara','Hambantota')
ON DUPLICATE KEY UPDATE status='active';
