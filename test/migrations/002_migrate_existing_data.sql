-- Migration: Migrate existing course-faculty relationships to new materii structure
-- This script helps transition existing data to the new materii-based system

USE [Grafica_UTCN]
GO

-- Step 1: Create temporary materii for each faculty to preserve existing course assignments
-- This creates a "General" materie for each faculty to hold existing courses
INSERT INTO [dbo].[materii] ([id], [name], [description], [year], [semester], [credits])
SELECT 
    NEWID() as id,
    f.name + ' - Materiale Generale' as name,
    'Materie temporară pentru migrarea cursurilor existente din facultatea ' + f.name as description,
    1 as year, -- Default to year 1
    1 as semester, -- Default to semester 1
    6 as credits -- Default credits
FROM [dbo].[faculty] f
GO

-- Step 2: Link these temporary materii to their respective faculties
INSERT INTO [dbo].[materii_faculties] ([materie_id], [faculty_id])
SELECT 
    m.id as materie_id,
    f.id as faculty_id
FROM [dbo].[materii] m
INNER JOIN [dbo].[faculty] f ON m.name = f.name + ' - Materiale Generale'
GO

-- Step 3: Migrate existing course-faculty relationships to course-materii relationships
INSERT INTO [dbo].[course_materii] ([course_id], [materie_id])
SELECT DISTINCT
    cf.course_id,
    m.id as materie_id
FROM [dbo].[course_faculties] cf
INNER JOIN [dbo].[faculty] f ON cf.faculty_id = f.id
INNER JOIN [dbo].[materii] m ON m.name = f.name + ' - Materiale Generale'
GO

-- Step 4: Verify the migration
SELECT 
    'Migration Summary' as Info,
    COUNT(*) as TotalMigratedCourses
FROM [dbo].[course_materii]

UNION ALL

SELECT 
    'Original course-faculty relationships' as Info,
    COUNT(*) as Count
FROM [dbo].[course_faculties]

UNION ALL

SELECT 
    'New materii created' as Info,
    COUNT(*) as Count
FROM [dbo].[materii]
WHERE name LIKE '% - Materiale Generale'

UNION ALL

SELECT 
    'Materii-faculty relationships' as Info,
    COUNT(*) as Count
FROM [dbo].[materii_faculties]
GO

-- Step 5: Create a view to show the migration results
CREATE VIEW [dbo].[v_migration_status] AS
SELECT 
    c.title as course_title,
    c.type as course_type,
    f.name as original_faculty,
    m.name as new_materie,
    cf.assigned_at as original_assignment_date,
    cm.assigned_at as new_assignment_date
FROM [dbo].[courses] c
LEFT JOIN [dbo].[course_faculties] cf ON c.id = cf.course_id
LEFT JOIN [dbo].[faculty] f ON cf.faculty_id = f.id
LEFT JOIN [dbo].[course_materii] cm ON c.id = cm.course_id
LEFT JOIN [dbo].[materii] m ON cm.materie_id = m.id
GO

-- Instructions for manual review
PRINT 'Data migration completed!'
PRINT ''
PRINT 'Please review the migration using:'
PRINT 'SELECT * FROM v_migration_status'
PRINT ''
PRINT 'After verification, you can:'
PRINT '1. Keep both old and new structures during transition period'
PRINT '2. Update application code to use new materii structure'
PRINT '3. Eventually drop course_faculties table when fully migrated'
PRINT ''
PRINT 'WARNING: Do not drop course_faculties table until application is updated!'
