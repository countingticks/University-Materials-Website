-- Migration: Add Materii and Semesters tables with many-to-many relationships
-- This migration creates the new structure for subjects (materii) and semester management

USE [Grafica_UTCN]
GO

-- Step 1: Create the materii (subjects) table
CREATE TABLE [dbo].[materii](
    [id] [uniqueidentifier] NOT NULL DEFAULT NEWID(),
    [name] [nvarchar](100) NOT NULL,
    [description] [nvarchar](500) NULL,
    [year] [int] NOT NULL, -- 1, 2, 3, 4 for university years
    [semester] [int] NOT NULL, -- 1 or 2
    [credits] [int] NULL, -- ECTS credits
    [created_at] [datetime2](7) NOT NULL DEFAULT GETDATE(),
    [updated_at] [datetime2](7) NOT NULL DEFAULT GETDATE(),
 CONSTRAINT [PK_materii] PRIMARY KEY CLUSTERED 
(
    [id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO

-- Step 2: Create many-to-many relationship between materii and faculties
CREATE TABLE [dbo].[materii_faculties](
    [materie_id] [uniqueidentifier] NOT NULL,
    [faculty_id] [uniqueidentifier] NOT NULL,
    [assigned_at] [datetime2](7) NOT NULL DEFAULT GETDATE(),
PRIMARY KEY CLUSTERED 
(
    [materie_id] ASC,
    [faculty_id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO

-- Step 3: Create many-to-many relationship between courses and materii
CREATE TABLE [dbo].[course_materii](
    [course_id] [uniqueidentifier] NOT NULL,
    [materie_id] [uniqueidentifier] NOT NULL,
    [assigned_at] [datetime2](7) NOT NULL DEFAULT GETDATE(),
PRIMARY KEY CLUSTERED 
(
    [course_id] ASC,
    [materie_id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO

-- Step 4: Create semesters table for period management
CREATE TABLE [dbo].[semesters](
    [id] [uniqueidentifier] NOT NULL DEFAULT NEWID(),
    [academic_year] [nvarchar](20) NOT NULL, -- e.g., "2024-2025"
    [semester_number] [int] NOT NULL, -- 1 or 2
    [start_date] [date] NOT NULL,
    [end_date] [date] NOT NULL,
    [is_active] [bit] NOT NULL DEFAULT 0,
    [created_at] [datetime2](7) NOT NULL DEFAULT GETDATE(),
    [updated_at] [datetime2](7) NOT NULL DEFAULT GETDATE(),
 CONSTRAINT [PK_semesters] PRIMARY KEY CLUSTERED 
(
    [id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO

-- Step 5: Add foreign key constraints for materii_faculties
ALTER TABLE [dbo].[materii_faculties] WITH CHECK ADD FOREIGN KEY([materie_id])
REFERENCES [dbo].[materii] ([id])
ON DELETE CASCADE
GO

ALTER TABLE [dbo].[materii_faculties] WITH CHECK ADD FOREIGN KEY([faculty_id])
REFERENCES [dbo].[faculty] ([id])
ON DELETE CASCADE
GO

-- Step 6: Add foreign key constraints for course_materii
ALTER TABLE [dbo].[course_materii] WITH CHECK ADD FOREIGN KEY([course_id])
REFERENCES [dbo].[courses] ([id])
ON DELETE CASCADE
GO

ALTER TABLE [dbo].[course_materii] WITH CHECK ADD FOREIGN KEY([materie_id])
REFERENCES [dbo].[materii] ([id])
ON DELETE CASCADE
GO

-- Step 7: Add constraints for data validation
ALTER TABLE [dbo].[materii] WITH CHECK ADD CONSTRAINT [CK_materii_year] 
CHECK ([year] >= 1 AND [year] <= 4)
GO

ALTER TABLE [dbo].[materii] WITH CHECK ADD CONSTRAINT [CK_materii_semester] 
CHECK ([semester] IN (1, 2))
GO

ALTER TABLE [dbo].[semesters] WITH CHECK ADD CONSTRAINT [CK_semesters_number] 
CHECK ([semester_number] IN (1, 2))
GO

ALTER TABLE [dbo].[semesters] WITH CHECK ADD CONSTRAINT [CK_semesters_dates] 
CHECK ([end_date] > [start_date])
GO

-- Step 8: Create unique constraint to prevent duplicate active semesters
ALTER TABLE [dbo].[semesters] WITH CHECK ADD CONSTRAINT [UQ_semesters_active] 
UNIQUE ([academic_year], [semester_number])
GO

-- Step 9: Add indexes for better performance
CREATE NONCLUSTERED INDEX [IX_materii_year_semester] ON [dbo].[materii]
(
    [year] ASC,
    [semester] ASC
)
GO

CREATE NONCLUSTERED INDEX [IX_semesters_active] ON [dbo].[semesters]
(
    [is_active] ASC,
    [academic_year] ASC,
    [semester_number] ASC
)
GO

-- Step 10: Insert sample data for testing
-- Sample materii
INSERT INTO [dbo].[materii] ([id], [name], [description], [year], [semester], [credits])
VALUES 
    (NEWID(), 'Grafica pe Calculator', 'Fundamentele grafirii computerizate și a prelucrării imaginilor', 2, 1, 6),
    (NEWID(), 'Algoritmi și Structuri de Date', 'Algoritmi fundamentali și structuri de date eficiente', 2, 1, 6),
    (NEWID(), 'Baze de Date', 'Proiectarea și implementarea bazelor de date relaționale', 2, 2, 6),
    (NEWID(), 'Programare Orientată pe Obiecte', 'Concepte și tehnici de programare orientată pe obiecte', 2, 2, 6)
GO

-- Sample semester periods
INSERT INTO [dbo].[semesters] ([id], [academic_year], [semester_number], [start_date], [end_date], [is_active])
VALUES 
    (NEWID(), '2024-2025', 1, '2024-10-01', '2025-02-28', 1),
    (NEWID(), '2024-2025', 2, '2025-03-01', '2025-09-30', 0),
    (NEWID(), '2025-2026', 1, '2025-10-01', '2026-02-28', 0),
    (NEWID(), '2025-2026', 2, '2026-03-01', '2026-09-30', 0)
GO

PRINT 'Migration completed successfully!'
PRINT 'New tables created: materii, materii_faculties, course_materii, semesters'
PRINT 'Next steps:'
PRINT '1. Migrate existing course-faculty relationships to course-materii relationships'
PRINT '2. Update application code to work with new structure'
PRINT '3. Add admin interface for managing materii and semesters'
