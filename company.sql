
CREATE SEQUENCE employee_sequence;
CREATE SEQUENCE management_sequence;

CREATE TABLE employee (
       employee_id integer DEFAULT NEXTVAL('employee_sequence') PRIMARY KEY,
       name varchar(256),
       telecommutes boolean
);


CREATE TABLE management (
       management_id integer DEFAULT NEXTVAL('management_sequence') PRIMARY KEY,
       manager int REFERENCES employee (employee_id),
       managed int REFERENCES employee (employee_id)
);

INSERT INTO employee (name,telecommutes) VALUES ('Lex' ,'f');
INSERT INTO employee (name,telecommutes) VALUES ('Gustavo','f');
INSERT INTO employee (name,telecommutes) VALUES ('Xavier','f');
INSERT INTO employee (name,telecommutes) VALUES ('Eugene','t');
INSERT INTO employee (name,telecommutes) VALUES ('Lei','f');
INSERT INTO employee (name,telecommutes) VALUES ('Salman','f');
INSERT INTO employee (name,telecommutes) VALUES ('Greg','f');
INSERT INTO employee (name,telecommutes) VALUES ('Bob','t');

INSERT INTO management (manager,managed) 
    SELECT emp1.employee_id,emp2.employee_id
      FROM employee AS emp1,employee AS emp2 WHERE emp1.name='Lex' AND emp2.name='Gustavo';

INSERT INTO management (manager,managed) 
    SELECT emp1.employee_id,emp2.employee_id
      FROM employee AS emp1,employee AS emp2 WHERE emp1.name='Gustavo' AND emp2.name='Eugene';

INSERT INTO management (manager,managed) 
    SELECT emp1.employee_id,emp2.employee_id
      FROM employee AS emp1,employee AS emp2 WHERE emp1.name='Gustavo' AND emp2.name='Salman';

INSERT INTO management (manager,managed) 
    SELECT emp1.employee_id,emp2.employee_id
      FROM employee AS emp1,employee AS emp2 WHERE emp1.name='Lex' AND emp2.name='Lei';

INSERT INTO management (manager,managed) 
    SELECT emp1.employee_id,emp2.employee_id
      FROM employee AS emp1,employee AS emp2 WHERE emp1.name='Lex' AND emp2.name='Xavier';

INSERT INTO management (manager,managed) 
    SELECT emp1.employee_id,emp2.employee_id
      FROM employee AS emp1,employee AS emp2 WHERE emp1.name='Salman' AND emp2.name='Greg';

INSERT INTO management (manager,managed) 
    SELECT emp1.employee_id,emp2.employee_id
      FROM employee AS emp1,employee AS emp2 WHERE emp1.name='Eugene' AND emp2.name='Bob';
