-- Two more rotating avatar slots (spec: "add two more pictures in the
-- headshot/logo space"). The JS oscillator (app.js) and .avatar-img CSS were
-- already written generically over any number of frames, so this is purely
-- additive: more optional image slots feeding the same rotation.
ALTER TABLE profile
    ADD COLUMN photo3_url VARCHAR(512) NULL AFTER logo_url,
    ADD COLUMN photo4_url VARCHAR(512) NULL AFTER photo3_url;
