ALTER TABLE proposals
    ADD CONSTRAINT proposals_parent_version_unique
        UNIQUE (parent_id, version);
